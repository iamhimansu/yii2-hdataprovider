<?php

namespace iamhimansu\hdataprovider;

use Yii;

class HActiveTableColumns
{
    public $attributes;

    /**
     * @param $configs
     * @return HActiveTableColumns
     * @throws \yii\base\InvalidConfigException
     */
    public static function create($configs)
    {
        return Yii::createObject(array_merge([
            'class' => __CLASS__,
        ], $configs));
    }

    /**
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    public function __toString()
    {
        return serialize($this);
    }
}