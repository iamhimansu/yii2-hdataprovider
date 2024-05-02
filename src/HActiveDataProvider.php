<?php

namespace iamhimansu\hdataprovider;

use Exception;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Html;

class HActiveDataProvider extends ActiveDataProvider
{
    /**
     * @var HActiveDataProvider
     */
    public $dataProvider;
    /**
     * @var ActiveQuery
     */
    private $_query;
    /**
     * @var HQueryBuilder
     */
    private $_queryBuilder;
    /**
     * @var ActiveRecord
     */
    private $_primaryModel;

    /**
     * @var $_tablesUsed array
     */
    private $_tablesUsed;

    /**
     * Contains the attributes of all the joined tables
     * @var $_attributes HActiveTableAttributes[]
     */
    private $_attributes;

    /**
     * Condition symbols
     */
    const NOT_EQUALS_CONDITION_SYMBOL = '<>';
    const EQUALS_CONDITION_SYMBOL = '=';
    const LIKE_CONDITION_SYMBOL = 'LIKE';
    const LESS_THAN_CONDITION_SYMBOL = '<';
    const GREATER_THAN_CONDITION_SYMBOL = '>';
    const IN_CONDITION_SYMBOL = 'IN';
    const BETWEEN_CONDITION_SYMBOL = 'BETWEEN';

    /**
     * Filter Symbols
     */
    const OR_FILTER_SYMBOL = 'OR';
    const AND_FILTER_SYMBOL = 'AND';

    /**
     * @param $configs
     * @return object
     * @throws \yii\base\InvalidConfigException
     */

    public static function filter($configs = [])
    {
        return Yii::createObject(array_merge([
                'class' => __CLASS__,
            ], $configs)
        );
    }

    public function init()
    {
        if (empty($this->dataProvider)) {
            throw new Exception('DataProvider cannot be empty.');
        }

        /**
         * Load primaryModel, queryBuilder, query into self
         * @see  self::_primaryModel
         * @see  self::_queryBuilder
         * @see  self::_query
         */
        $this->initFromDataProvider();

        $this->extractTablesUsed();

        $this->extractTableAttributes();

    }

    /**
     * Generates the layout
     * @return string
     */
    public function prepareLayout()
    {
        ob_start();
        ob_implicit_flush(false);

        echo Html::beginForm([null], 'GET');
        $tableAttributes = $this->_attributes;

        $conditionSymbols = self::getAllConditionSymbols();
        $filterSymbols = self::getAllFilterSymbols();

        $html = [];
        $fillers = $fillersContent = [];
        $count = 0;
        foreach ($tableAttributes as $tablesAlias => $HActiveTableAttributes) {

            $tableAttributeLayout = HActiveTableAttributeLayout::create([
                'dataProvider' => $this->dataProvider,
                'tableData' => $this->_tablesUsed[$tablesAlias],
                'fields' => $HActiveTableAttributes
            ]);

            $html[$tablesAlias][] = $tableAttributeLayout->prepare();

            $fillers[] = '[[TABLE]]';

            $fillersContent[$tablesAlias] = implode("\n", [
                '<table>',
                '<tr>',
                '<td colspan="4">',
                Html::dropDownList("$tablesAlias-filters", null, $filterSymbols, [
                    'class' => 'form form-control'
                ]),
                '</td>',
                '</tr>',
                '</table>'
            ]);

        }
        
        $tableFilters = array_map(function ($elements) {
            return implode("\n", $elements);
        }, $html);

        $content = implode("[[TABLE]]", $tableFilters);

        echo str_replace($fillers, $fillersContent, $content);

        echo Html::submitButton('Search', [
            'class' => 'btn btn-primary'
        ]);
        echo Html::endForm();

        return ob_get_clean();
    }

    /**
     * @return string
     * Renders the dataprovider
     */
    private function render()
    {
        return $this->prepareLayout();
    }

    /**
     * Loads the dataprovider
     * @return
     */
    private function initFromDataProvider()
    {
        $this->_primaryModel = $this->dataProvider->query->modelClass;
        $this->_queryBuilder = new HQueryBuilder($this->dataProvider->query->modelClass::getDb());
        $this->_query = $this->dataProvider->query;
    }

    /**
     * @return mixed
     */
    public function getPrimaryModel()
    {
        return $this->_primaryModel;
    }

    /**
     * Extracts tables used with joins with alias and table names
     * ```php
     * [
     * 'u' => 'users',
     * 'c' => 'customers'
     * ]
     * ```
     * @return array
     * @throws \yii\base\NotSupportedException
     * @throws \yii\db\Exception
     */
    private function extractTablesUsed()
    {
        $db = $this->_primaryModel::getDb();

        //Add the base table
        $tablenAlias = $this->_query->getTablesUsedInFrom();

        foreach ($tablenAlias as $alias => $table) {
            /**
             * Remove {{ }} from the table n alias
             */
            $simpleTableAlias = str_replace(['{{', '}}'], '', "$table $alias");
            list($tableName, $tableAlias) = explode(' ', $simpleTableAlias);
            $this->_tablesUsed[$tableAlias] = [
                'tableName' => $tableName,
                'modelClass' => $this->getPrimaryModel(),
                'alias' => $tableAlias
            ];
        }

        $joins = (array)$this->_query->join;

        foreach ($joins as $i => $join) {

            if (!is_array($join) || !isset($join[0], $join[1])) {
                throw new \yii\db\Exception('A join clause must be specified as an array of join type, join table, and optionally join condition.');
            }

            // 0:join type, 1:join table, 2:on-condition (optional)
            list(, $table) = $join;

            /**
             * For nested joins
             */
            if (is_array($table)) {
                foreach ($table as $joinAlias => $query) {
                    /** @var $query ActiveQuery */
                    list($from) = array_values($query->from);
                    $this->_tablesUsed[$joinAlias] = [
                        'tableName' => $db->getSchema()->unquoteSimpleTableName($from),
                        'modelClass' => $query->modelClass,
                        'alias' => $joinAlias
                    ];
                }
            } else {
                list($tableName, $alias) = explode(' ', $db->getSchema()->unquoteSimpleTableName($table));
                if (empty($alias)) {
                    $alias = $tableName;
                }
                $this->_tablesUsed[$alias] = [
                    'tableName' => $tableName,
                    'modelClass' => null,
                    'alias' => $alias
                ];
            }
        }
        return $this->_tablesUsed;
    }

    /**
     * Extracts the table attributes
     * ```php
     * [
     * 'u' => ['id', 'name', ...],
     * 'c' => ['id', 'name', ...]
     * ]
     * @return void
     */
    private function extractTableAttributes()
    {
        $db = $this->_primaryModel::getDb();
        foreach ($this->_tablesUsed as $tableAlias => $table) {
            $tableName = $table['tableName'];
            $this->_attributes[$tableAlias] = HActiveTableColumns::create([
                'attributes' => $db->getTableSchema($tableName)->getColumnNames()
            ]);
        }
    }

    public function __toString()
    {
        return $this->render();
    }

    /**
     * Gets all conditions
     * @return string[]
     */
    public static function getAllConditionSymbols()
    {
        return [
            self::EQUALS_CONDITION_SYMBOL => self::EQUALS_CONDITION_SYMBOL,
            self::NOT_EQUALS_CONDITION_SYMBOL => self::NOT_EQUALS_CONDITION_SYMBOL,
            self::GREATER_THAN_CONDITION_SYMBOL => self::GREATER_THAN_CONDITION_SYMBOL,
            self::LESS_THAN_CONDITION_SYMBOL => self::LESS_THAN_CONDITION_SYMBOL,
            self::LIKE_CONDITION_SYMBOL => self::LIKE_CONDITION_SYMBOL,
            self::IN_CONDITION_SYMBOL => self::IN_CONDITION_SYMBOL,
            self::BETWEEN_CONDITION_SYMBOL => self::BETWEEN_CONDITION_SYMBOL
        ];
    }

    /**
     * Get all conditional symbols
     * @return string[]
     */
    public static function getAllFilterSymbols()
    {
        return [
            self::OR_FILTER_SYMBOL => self::OR_FILTER_SYMBOL,
            self::AND_FILTER_SYMBOL => self::AND_FILTER_SYMBOL
        ];
    }
}