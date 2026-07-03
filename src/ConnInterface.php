<?php

namespace Lin\Lite;

interface ConnInterface
{
    public function getSymbol(): string;
    public function query(string $sql, array $args = []);
    public function exec(string $sql, array $args = []);
    public function lastInsertId(): int|string;
    public function begin();
    public function commit();
    public function rollback();
}