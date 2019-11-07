(function (angular, $, _) {
  var module = angular.module('civicase');

  module.service('TagsActivityAction', TagsActivityAction);

  function TagsActivityAction ($rootScope, crmApi, dialogService) {
    /**
     * Add/Remove tags to activities
     *
     * @param {String} operation
     * @param {Array} activities
     * @param {Boolean} isSelectAll
     * @param {Object} params
     * @param {int} totalCount
     */
    this.manageTags = function (operation, activities, isSelectAll, params, totalCount) {
      var title, saveButtonLabel;
      title = saveButtonLabel = 'Tag Activities';

      if (operation === 'remove') {
        title += ' (Remove)';
        saveButtonLabel = 'Remove tags from Activities';
      }

      getTags()
        .then(function (tags) {
          var model = setModelObjectForModal(tags, activities.length || totalCount);

          openTagsModal(model, title, saveButtonLabel, operation, {
            selectedActivities: activities,
            isSelectAll: isSelectAll,
            searchParams: params
          });
        });
    };

    /**
     * Set the model object to be used in the modal
     *
     * @param {Array} tags
     * @param {int} numberOfActivities
     * @return {Object}
     */
    function setModelObjectForModal (tags, numberOfActivities) {
      var model = {};

      model.selectedActivitiesLength = numberOfActivities;
      model.genericTags = prepareGenericTags(tags);
      model.tagSets = prepareTagSetsTree(tags);

      model.selectedGenericTags = [];
      model.toggleGenericTags = toggleGenericTags;

      return model;
    }

    /**
     * Toggle the State of Generic tags
     *
     * @param {Object} model
     * @param {String} tagID
     */
    function toggleGenericTags (model, tagID) {
      if (model.selectedGenericTags.indexOf(tagID) === -1) {
        model.selectedGenericTags.push(tagID);
      } else {
        model.selectedGenericTags = _.reject(model.selectedGenericTags, function (tag) {
          return tag === tagID;
        });
      }
    }

    /**
     * Opens the modal for addition/removal of tags
     *
     * @param {Object} model
     * @param {String} title
     * @param {String} saveButtonLabel
     * @param {String} operation
     * @param {Object} activitiesObject
     */
    function openTagsModal (model, title, saveButtonLabel, operation, activitiesObject) {
      dialogService.open('TagsActivityAction', '~/civicase/activity/actions/services/tags-activity-action.html', model, {
        autoOpen: false,
        height: 'auto',
        width: '40%',
        title: title,
        buttons: [{
          text: saveButtonLabel,
          icons: operation === 'add' ? {primary: 'fa-check'} : false,
          click: function () {
            addRemoveTagsConfirmationHandler.call(this, operation, activitiesObject, model);
          }
        }]
      });
    }

    /**
     * Add/Remove tags confirmation handler
     *
     * @param {String} operation
     * @param {Object} activitiesObject
     * @param {Object} model
     * @return {Promise}
     */
    function addRemoveTagsConfirmationHandler (operation, activitiesObject, model) {
      var tagIds = model.selectedGenericTags;

      _.each(model.tagSets, function (tag) {
        if (tag.selectedTags) {
          tagIds = tagIds.concat(JSON.parse('[' + tag.selectedTags + ']'));
        }
      });

      var apiCalls = prepareApiCalls(operation, activitiesObject, tagIds);

      crmApi(apiCalls)
        .then(function () {
          $rootScope.$broadcast('civicase::activity::updated');
        });

      $(this).dialog('close');
    }

    /**
     * Prepare the API calls for the Add/Remove operation
     *
     * @param {String} operation
     * @param {Object} activitiesObject
     * @param {Array} tagIds
     * @return {Array}
     */
    function prepareApiCalls (operation, activitiesObject, tagIds) {
      var action = operation === 'add' ? 'createByQuery' : 'deleteByQuery';

      if (activitiesObject.isSelectAll) {
        return [['EntityTag', action, {
          entity_table: 'civicrm_activity',
          tag_id: tagIds,
          params: activitiesObject.searchParams
        }]];
      } else {
        return [['EntityTag', action, {
          entity_table: 'civicrm_activity',
          tag_id: tagIds,
          entity_id: activitiesObject.selectedActivities.map(function (activity) {
            return activity.id;
          })
        }]];
      }
    }

    /**
     * Get the tags for Activities from API end point
     *
     * @return {Promise}
     */
    function getTags () {
      return crmApi('Tag', 'get', {
        'sequential': 1,
        'used_for': { 'LIKE': '%civicrm_activity%' }
      }).then(function (data) {
        return data.values;
      });
    }

    /**
     * Recursive function to prepare the generic tags
     *
     * @param {Array} tags
     * @param {String} parentID
     * @param {int} level
     * @return {Array}
     */
    function prepareGenericTags (tags, parentID, level) {
      var returnArray = [];

      level = typeof level !== 'undefined' ? level : 0;
      parentID = typeof parent !== 'undefined' ? parentID : undefined;

      var filteredTags = _.filter(tags, function (child) {
        return child.parent_id === parentID && child.is_tagset === '0';
      });

      if (_.isEmpty(filteredTags)) {
        return [];
      }

      _.each(filteredTags, function (tag) {
        returnArray.push(tag);
        tag.indentationLevel = level;
        returnArray = returnArray.concat(prepareGenericTags(tags, tag.id, level + 1));
      });

      return returnArray;
    }

    /**
     * Prepares the tag sets tree
     *
     * @param {Array} tags
     * @return {Array}
     */
    function prepareTagSetsTree (tags) {
      var returnArray = [];

      var filteredTags = _.filter(tags, function (child) {
        return !child.parent_id && child.is_tagset === '1';
      });

      if (_.isEmpty(filteredTags)) {
        return [];
      }

      _.each(filteredTags, function (tag) {
        var children = _.filter(tags, function (child) {
          if (child.parent_id === tag.id && child.is_tagset === '0') {
            child.text = child.name;
            return true;
          }
        });

        if (children.length > 0) {
          tag.children = children;
        }

        returnArray.push(tag);
      });

      return returnArray;
    }
  }
})(angular, CRM.$, CRM._);
