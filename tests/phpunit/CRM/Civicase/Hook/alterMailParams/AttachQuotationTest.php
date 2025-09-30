<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

require_once __DIR__ . '/../../../../../BaseHeadlessTest.php';

/**
 * Tests for AttachQuotation functionality and memory optimization.
 *
 * @group headless
 */
class CRM_Civicase_Hook_alterMailParams_AttachQuotationTest extends BaseHeadlessTest implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  /**
   * Test basic functionality - should handle cases where no quotation exists.
   */
  public function testHandleWithoutQuotation() {
    $params = [
      'tokenContext' => ['contributionId' => 999999], // Non-existent contribution
      'attachments' => []
    ];
    $context = 'test';
    
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    
    // Should handle gracefully without throwing exceptions
    $attachQuotation->run($params, $context);
    
    // No attachment should be added
    $this->assertArrayNotHasKey('quotaition_invoice', $params['attachments']);
  }

  /**
   * Test LRU cache functionality for contribution to quotation mapping.
   */
  public function testContributionQuotationLRUCache() {
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    $reflection = new ReflectionClass($attachQuotation);
    
    // Get private methods
    $addToLRUCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToLRUCacheMethod->setAccessible(TRUE);
    $updateLRUOrderMethod = $reflection->getMethod('updateLRUOrder');
    $updateLRUOrderMethod->setAccessible(TRUE);
    
    $cache = [];
    $order = [];
    
    // Test adding items to cache
    $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, 'contrib1', 'quotation1');
    $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, 'contrib2', 'quotation2');
    $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, 'contrib3', 'quotation3');
    
    $this->assertCount(3, $cache);
    $this->assertCount(3, $order);
    $this->assertEquals('quotation1', $cache['contrib1']);
    $this->assertEquals(['contrib1', 'contrib2', 'contrib3'], $order);
    
    // Test LRU order update
    $updateLRUOrderMethod->invoke($attachQuotation, $order, 'contrib1');
    $this->assertEquals(['contrib2', 'contrib3', 'contrib1'], $order);
  }

  /**
   * Test cache eviction when at capacity.
   */
  public function testCacheEvictionAtCapacity() {
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    $reflection = new ReflectionClass($attachQuotation);
    
    $addToLRUCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToLRUCacheMethod->setAccessible(TRUE);
    $maxCacheSizeProperty = $reflection->getProperty('maxCacheSize');
    $maxCacheSizeProperty->setAccessible(TRUE);
    
    // Set small cache size for testing
    $maxCacheSizeProperty->setValue($attachQuotation, 3);
    
    $cache = [];
    $order = [];
    
    // Fill cache to capacity
    for ($i = 1; $i <= 3; $i++) {
      $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, $i, "quotation$i");
    }
    
    $this->assertCount(3, $cache);
    $this->assertTrue(isset($cache[1]));
    
    // Add one more item - should evict LRU
    $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, 4, "quotation4");
    
    $this->assertCount(3, $cache);
    $this->assertFalse(isset($cache[1])); // First item should be evicted
    $this->assertTrue(isset($cache[4])); // New item should be added
  }

  /**
   * Test memory management during bulk processing.
   */
  public function testMemoryManagementDuringBulkProcessing() {
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    $reflection = new ReflectionClass($attachQuotation);
    
    $processedQuotationsProperty = $reflection->getProperty('processedQuotations');
    $processedQuotationsProperty->setAccessible(TRUE);
    $performMemoryManagementMethod = $reflection->getMethod('performMemoryManagement');
    $performMemoryManagementMethod->setAccessible(TRUE);
    
    // Set counter to trigger memory management
    $processedQuotationsProperty->setValue($attachQuotation, 25);
    
    // Test that memory management runs without errors
    $this->assertNull($performMemoryManagementMethod->invoke($attachQuotation));
  }

  /**
   * Test updating existing cache entries.
   */
  public function testUpdatingExistingCacheEntries() {
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    $reflection = new ReflectionClass($attachQuotation);
    
    $addToLRUCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToLRUCacheMethod->setAccessible(TRUE);
    
    $cache = [];
    $order = [];
    
    // Add initial entry
    $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, 'key1', 'value1');
    $this->assertEquals('value1', $cache['key1']);
    $this->assertEquals(['key1'], $order);
    
    // Update existing entry
    $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, 'key1', 'updated_value1');
    $this->assertEquals('updated_value1', $cache['key1']);
    $this->assertCount(1, $cache);
    $this->assertEquals(['key1'], $order); // Should still be at end
  }

  /**
   * Test shouldRun method conditions.
   */
  public function testShouldRunConditions() {
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    $reflection = new ReflectionClass($attachQuotation);
    
    $shouldRunMethod = $reflection->getMethod('shouldRun');
    $shouldRunMethod->setAccessible(TRUE);
    
    // Test with component = 'contribute' and attach_quote set
    $_REQUEST['attach_quote'] = '1';
    $params = ['tplParams' => ['component' => 'contribute']];
    $result = $shouldRunMethod->invoke($attachQuotation, $params, 'test', '1');
    $this->assertTrue($result);
    
    // Test with component != 'contribute'
    $params = ['tplParams' => ['component' => 'other']];
    $result = $shouldRunMethod->invoke($attachQuotation, $params, 'test', '1');
    $this->assertFalse($result);
    
    // Test with empty attach_quote
    $params = ['tplParams' => ['component' => 'contribute']];
    $result = $shouldRunMethod->invoke($attachQuotation, $params, 'test', '');
    $this->assertFalse($result);
    
    unset($_REQUEST['attach_quote']);
  }

  /**
   * Test cache size limits are respected.
   */
  public function testCacheSizeLimits() {
    $attachQuotation = new CRM_Civicase_Hook_alterMailParams_AttachQuotation();
    $reflection = new ReflectionClass($attachQuotation);
    
    $maxCacheSizeProperty = $reflection->getProperty('maxCacheSize');
    $maxCacheSizeProperty->setAccessible(TRUE);
    $addToLRUCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToLRUCacheMethod->setAccessible(TRUE);
    
    // Set small cache size for testing
    $maxCacheSizeProperty->setValue($attachQuotation, 2);
    
    $cache = [];
    $order = [];
    
    // Add more items than cache can hold
    for ($i = 1; $i <= 5; $i++) {
      $addToLRUCacheMethod->invoke($attachQuotation, $cache, $order, "key$i", "value$i");
    }
    
    // Cache should never exceed max size
    $this->assertLessThanOrEqual(2, count($cache));
    $this->assertLessThanOrEqual(2, count($order));
    
    // Most recent items should be retained
    $this->assertTrue(isset($cache['key4']) || isset($cache['key5']));
  }

}