<?php

namespace iamhimansu\hdataprovider;

use yii\db\Query;

class HQueryBuilder extends \yii\db\QueryBuilder
{
    /**
     * Quotes table names passed.
     *
     * @param array $tables
     * @param array $params
     * @return array
     */
    public function quoteTableNames($tables, &$params)
    {
        foreach ($tables as $i => $table) {
            if ($table instanceof Query) {
                list($sql, $params) = $this->build($table, $params);
                $tables[$i] = "($sql) " . $this->db->quoteTableName($i);
            } elseif (is_string($i)) {
                if (strpos($table, '(') === false) {
                    $table = $this->db->quoteTableName($table);
                }
                $tables[$i] = "$table " . $this->db->quoteTableName($i);
            } elseif (strpos($table, '(') === false) {
                if ($tableWithAlias = $this->extractAlias($table)) { // with alias
                    $tables[$i] = $this->db->quoteTableName($tableWithAlias[1]) . ' ' . $this->db->quoteTableName($tableWithAlias[2]);
                } else {
                    $tables[$i] = $this->db->quoteTableName($table);
                }
            }
        }

        return $tables;
    }
}