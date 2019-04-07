<?php

namespace verbb\feedme\elements;

use Cake\Utility\Hash;
use Craft;
use craft\elements\Category as CategoryElement;
use verbb\feedme\base\Element;
use verbb\feedme\base\ElementInterface;
use verbb\feedme\Plugin;

class Category extends Element implements ElementInterface
{
    // Properties
    // =========================================================================

    public static $name = 'Category';
    public static $class = 'craft\elements\Category';

    public $element;


    // Templates
    // =========================================================================

    public function getGroupsTemplate()
    {
        return 'feed-me/_includes/elements/category/groups';
    }

    public function getColumnTemplate()
    {
        return 'feed-me/_includes/elements/category/column';
    }

    public function getMappingTemplate()
    {
        return 'feed-me/_includes/elements/category/map';
    }


    // Public Methods
    // =========================================================================

    public function getGroups()
    {
        return Craft::$app->categories->getEditableGroups();
    }

    public function getQuery($settings, $params = [])
    {
        $query = CategoryElement::find();

        $criteria = array_merge([
            'status' => null,
            'groupId' => $settings['elementGroup'][CategoryElement::class],
        ], $params);

        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $criteria['siteId'] = $siteId;
        }

        Craft::configure($query, $criteria);

        return $query;
    }

    public function setModel($settings)
    {
        $this->element = new CategoryElement();
        $this->element->groupId = $settings['elementGroup'][CategoryElement::class];

        $siteId = Hash::get($settings, 'siteId');

        if ($siteId) {
            $this->element->siteId = $siteId;
        }

        return $this->element;
    }

    public function afterSave($data, $settings)
    {
        $parent = Hash::get($data, 'parent');

        if ($parent && $parent !== $this->element->id) {
            $parentCategory = CategoryElement::findOne(['id' => $parent]);

            Craft::$app->getStructures()->append($this->element->group->structureId, $this->element, $parentCategory);
        }
    }

    // Protected Methods
    // =========================================================================

    protected function parseParent($feedData, $fieldInfo)
    {
        $value = $this->fetchSimpleValue($feedData, $fieldInfo);

        $match = Hash::get($fieldInfo, 'options.match');
        $create = Hash::get($fieldInfo, 'options.create');

        // Element lookups must have a value to match against
        if ($value === null || $value === '') {
            return null;
        }

        $query = CategoryElement::find()
            ->status(null)
            ->andWhere(['=', $match, $value]);

        if (isset($this->feed['siteId']) && $this->feed['siteId']) {
            $query->siteId($this->feed['siteId']);
        }

        $element = $query->one();

        if ($element) {
            return $element->id;
        }

        // Check if we should create the element. But only if title is provided (for the moment)
        if ($create && $match === 'title') {
            $element = new CategoryElement();
            $element->title = $value;
            $element->groupId = $this->element->groupId;

            $propagate = isset($this->feed['siteId']) && $this->feed['siteId'] ? false : true;

            if (!Craft::$app->getElements()->saveElement($element, true, $propagate)) {
                Plugin::error('Category error: Could not create parent - `{e}`.', ['e' => json_encode($element->getErrors())]);
            } else {
                Plugin::info('Category `#{id}` added.', ['id' => $element->id]);
            }

            return $element->id;
        }

        return null;
    }

}
