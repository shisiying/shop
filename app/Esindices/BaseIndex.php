<?php
namespace App\Esindices;

abstract class BaseIndex{
    //定义索引的别名
    abstract static function getAliasName();
    //定义索引的type
    abstract static function getTypesName();
    //定义索引的类型
    abstract static function getProperties();

    //索引的相关配置
    abstract static function getSettings();

    //重建数据
    abstract static function rebuild($indexName,$type);
}