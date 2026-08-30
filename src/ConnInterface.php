<?php

namespace Lin\Lite;

interface ConnInterface
{
    public function getSymbol();
    public function query($sql, $args = array());
    public function exec($sql, $args = array());
    public function lastInsertId();
    public function begin();
    public function commit();
    public function rollback();
}