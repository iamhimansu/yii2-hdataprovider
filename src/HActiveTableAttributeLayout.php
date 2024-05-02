<?php

namespace iamhimansu\hdataprovider;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\Inflector;

class HActiveTableAttributeLayout
{
    /**
     * @var $dataProvider ActiveDataProvider
     */
    public $dataProvider;

    /**
     * @var $tableData array
     */
    public $tableData;
    /**
     * @var $fields HActiveTableColumns
     */
    public $fields;

    /**
     * @var $content string
     */
    public $content;

    /**
     * @param $configs
     * @return HActiveTableAttributeLayout
     * @throws \yii\base\InvalidConfigException
     */
    public static function create($configs)
    {
        return Yii::createObject(array_merge([
            'class' => __CLASS__,
        ], $configs));
    }

    public function prepare()
    {
        $content = [];

        $tableData = $this->tableData;

        $tableAlias = $tableData['alias'];

        /** @var $modelClass ActiveRecord */
        $modelClass = $this->tableData['modelClass'];

        if (empty($modelClass)) {
            $attributeLabels = [];
        } else {
            $modelClassInstance = $modelClass::instance();
            $attributeLabels = $modelClassInstance->attributeLabels();
        }

        foreach ($this->fields->attributes as $index => $attribute) {
            $aliasAttribute = "$tableAlias.$attribute";
            if (isset($attributeLabels[$attribute])) {
                $attributeLabel = $attributeLabels[$attribute];
            } else {
                $attributeLabel = Inflector::titleize($attribute);
            }
            $this->fields->attributes[$aliasAttribute] = $attributeLabel;
            unset($this->fields->attributes[$index]);
        }

        $content[] = '<table style="width: 100%">';
        $content[] = '<tr>';
        $content[] = '<td colspan="4">';
        $content[] = Html::dropDownList("$tableAlias-field", null, $this->fields->attributes, [
            'class' => 'form form-control'
        ]);
        $content[] = '</td>';
        $content[] = '<td colspan="4">';
        $content[] = Html::dropDownList("$tableAlias-condition", null, HActiveDataProvider::getAllConditionSymbols(), [
            'class' => 'form form-control'
        ]);
        $content[] = '</td>';
        $content[] = '<td colspan="4">';
        $content[] = Html::textInput("$tableAlias-value", null, [
            'class' => 'form form-control'
        ]);
        $content[] = '</td>';
        $content[] = '</tr>';
        $content[] = '</table>';

        $this->content = implode("\n", $content);
        return $this;
    }

    private function render()
    {
        return (string)$this->content;
    }

    public function __toString()
    {
        return $this->render();
    }
}