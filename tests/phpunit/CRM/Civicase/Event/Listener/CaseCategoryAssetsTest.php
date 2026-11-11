<?php

use Civi\Core\Event\GenericHookEvent;
use CRM_Civicase_Event_Listener_CaseCategoryAssets as CaseCategoryAssets;
use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Service_CaseCategoryCustomFieldsSetting as CaseCategoryCustomFieldsSetting;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;

/**
 * Test class for CRM_Civicase_Event_Listener_CaseCategoryAssets.
 *
 * @group headless
 */
class CRM_Civicase_Event_Listener_CaseCategoryAssetsTest extends BaseHeadlessTest {

  /**
   * The locale the tests run in.
   *
   * @var string
   */
  private $locale;

  /**
   * Word replacement statics before the test, restored in tearDown().
   *
   * @var array|null
   */
  private $originalLocaleStrings;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();
    $this->locale = CRM_Core_I18n::getLocale();
    $this->originalLocaleStrings = Civi::$statics[CRM_Core_I18n::class] ?? NULL;
    unset($_GET['case_type_category'], $_REQUEST['case_type_category']);
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown() {
    if ($this->originalLocaleStrings === NULL) {
      unset(Civi::$statics[CRM_Core_I18n::class]);
    }
    else {
      Civi::$statics[CRM_Core_I18n::class] = $this->originalLocaleStrings;
    }
    unset($_GET['case_type_category'], $_REQUEST['case_type_category']);
    parent::tearDown();
  }

  /**
   * The strings bundle gets the case category of the request.
   */
  public function testOnGetAssetUrlAddsTheRequestCategoryToTheStringsBundle() {
    $category = $this->createCaseTypeCategory('Awards', 'Award');
    $_GET['case_type_category'] = $_REQUEST['case_type_category'] = $category['value'];
    $params = ['modules' => 'crmUi'];

    CaseCategoryAssets::onGetAssetUrl($this->getAssetUrlEvent('angular-modules.json', $params));

    $this->assertEquals($category['name'], $params[CaseCategoryAssets::PARAM]);
    $this->assertEquals('crmUi', $params['modules']);
  }

  /**
   * Without a category in the request the bundle is built for Cases.
   */
  public function testOnGetAssetUrlUsesCasesWhenTheRequestHasNoCategory() {
    $params = [];

    CaseCategoryAssets::onGetAssetUrl($this->getAssetUrlEvent('angular-modules.json', $params));

    $this->assertEquals(CaseCategoryHelper::CASE_TYPE_CATEGORY_NAME, $params[CaseCategoryAssets::PARAM]);
  }

  /**
   * An unknown category in the request falls back to Cases.
   */
  public function testOnGetAssetUrlFallsBackToCasesForAnUnknownCategory() {
    $_GET['case_type_category'] = $_REQUEST['case_type_category'] = 999999;
    $params = [];

    CaseCategoryAssets::onGetAssetUrl($this->getAssetUrlEvent('angular-modules.json', $params));

    $this->assertEquals(CaseCategoryHelper::CASE_TYPE_CATEGORY_NAME, $params[CaseCategoryAssets::PARAM]);
  }

  /**
   * Bundles without translated strings are the same for every category.
   */
  public function testOnGetAssetUrlLeavesOtherBundlesUnchanged() {
    $category = $this->createCaseTypeCategory('Awards', 'Award');
    $_GET['case_type_category'] = $_REQUEST['case_type_category'] = $category['value'];

    foreach (['angular-modules.js', 'crm-menubar.css'] as $asset) {
      $params = ['modules' => 'crmUi'];
      CaseCategoryAssets::onGetAssetUrl($this->getAssetUrlEvent($asset, $params));
      $this->assertEquals(['modules' => 'crmUi'], $params, "$asset should not get a category");
    }
  }

  /**
   * The bundle gets its category's wording, then the request's comes back.
   */
  public function testBuildAssetUsesTheBundleCategoryWordingAndRestoresTheRequestWording() {
    $category = $this->createCaseTypeCategory('Awards', 'Award');
    $requestStrings = [
      'enabled' => ['wildcardMatch' => ['QA request only' => 'QA replaced']],
      'disabled' => [],
    ];
    Civi::$statics[CRM_Core_I18n::class][$this->locale] = $requestStrings;
    $event = $this->getBuildAssetEvent('angular-modules.json', [CaseCategoryAssets::PARAM => $category['name']]);

    CaseCategoryAssets::onBuildAssetBefore($event);
    $duringBuild = Civi::$statics[CRM_Core_I18n::class][$this->locale]['enabled']['wildcardMatch'] ?? [];
    CaseCategoryAssets::onBuildAssetAfter($event);

    $categoryWording = CaseCategoryHelper::getWordReplacements($category['name']);
    $this->assertNotEmpty($categoryWording);
    foreach ($categoryWording as $from => $to) {
      $this->assertEquals($to, $duringBuild[$from] ?? NULL, "'$from' should be replaced while the bundle is built");
    }
    $this->assertArrayNotHasKey('QA request only', $duringBuild, 'the request wording must not leak into the bundle');
    $this->assertSame($requestStrings, Civi::$statics[CRM_Core_I18n::class][$this->locale]);
  }

  /**
   * Word replacements not loaded before the build are not left loaded after it.
   */
  public function testBuildAssetLeavesUnloadedWordReplacementsUnloaded() {
    $category = $this->createCaseTypeCategory('Awards', 'Award');
    unset(Civi::$statics[CRM_Core_I18n::class][$this->locale]);
    $event = $this->getBuildAssetEvent('angular-modules.json', [CaseCategoryAssets::PARAM => $category['name']]);

    CaseCategoryAssets::onBuildAssetBefore($event);
    $this->assertArrayHasKey($this->locale, Civi::$statics[CRM_Core_I18n::class]);
    CaseCategoryAssets::onBuildAssetAfter($event);

    $this->assertArrayNotHasKey($this->locale, Civi::$statics[CRM_Core_I18n::class] ?? []);
  }

  /**
   * Other bundles, and bundles without a category, are built as before.
   */
  public function testBuildAssetLeavesOtherBundlesAlone() {
    $category = $this->createCaseTypeCategory('Awards', 'Award');
    $requestStrings = ['enabled' => ['wildcardMatch' => ['QA request only' => 'QA replaced']]];
    Civi::$statics[CRM_Core_I18n::class][$this->locale] = $requestStrings;

    $events = [
      $this->getBuildAssetEvent('angular-modules.js', [CaseCategoryAssets::PARAM => $category['name']]),
      $this->getBuildAssetEvent('angular-modules.json', []),
    ];
    foreach ($events as $event) {
      CaseCategoryAssets::onBuildAssetBefore($event);
      $this->assertSame($requestStrings, Civi::$statics[CRM_Core_I18n::class][$this->locale]);
      CaseCategoryAssets::onBuildAssetAfter($event);
      $this->assertSame($requestStrings, Civi::$statics[CRM_Core_I18n::class][$this->locale]);
    }
  }

  /**
   * Fabricates a case type category with its singular label.
   *
   * @param string $label
   *   Category label.
   * @param string $singularLabel
   *   Category singular label.
   *
   * @return array
   *   Case type category option value.
   */
  private function createCaseTypeCategory($label, $singularLabel) {
    $category = CaseCategoryFabricator::fabricate(['label' => $label]);
    (new CaseCategoryCustomFieldsSetting())->save(
      $category['value'], ['singular_label' => $singularLabel]
    );

    return $category;
  }

  /**
   * Returns a hook_civicrm_getAssetUrl event.
   *
   * @param string $asset
   *   Asset name.
   * @param array $params
   *   Asset parameters, changed by reference by the listener.
   *
   * @return \Civi\Core\Event\GenericHookEvent
   *   Event.
   */
  private function getAssetUrlEvent($asset, array &$params) {
    return GenericHookEvent::create(['asset' => $asset, 'params' => &$params]);
  }

  /**
   * Returns a hook_civicrm_buildAsset event.
   *
   * @param string $asset
   *   Asset name.
   * @param array $params
   *   Asset parameters.
   *
   * @return \Civi\Core\Event\GenericHookEvent
   *   Event.
   */
  private function getBuildAssetEvent($asset, array $params) {
    $mimeType = NULL;
    $content = NULL;

    return GenericHookEvent::create([
      'asset' => $asset,
      'params' => $params,
      'mimeType' => &$mimeType,
      'content' => &$content,
    ]);
  }

}
