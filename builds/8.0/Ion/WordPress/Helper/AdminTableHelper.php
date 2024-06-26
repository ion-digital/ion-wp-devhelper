<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace Ion\WordPress\Helper;

use Ion\WordPress\Helper\Constants;
use Ion\WordPress\WordPressHelper as WP;
use Ion\PhpHelper as PHP;
/**
 * Description of AdminTableHelper
 *
 * @author Justus
 */
class AdminTableHelper implements AdminTableHelperInterface
{
    private static function createGroupDescriptorInstance(
        /* string */
        $title = null,
        /* string */
        $id = null,
        array $columns = []
    )
    {
        return ["id" => (string) $id, "title" => (string) $title, "columns" => (array) $columns];
    }
    private static $detailMode = false;
    protected static function setDetailMode(bool $detailMode) : void
    {
        self::$detailMode = $detailMode;
        return;
    }
    public static function inDetailMode() : bool
    {
        return self::$detailMode;
    }
    private $parent;
    private $columnGroup;
    private $onReadHandlers;
    private $onDeleteHandlers;
    public function __construct(array &$parent)
    {
        $this->parent =& $parent;
        $this->columnGroup =& $parent["columnGroups"][0];
        $this->onReadHandlers = [];
        $this->onDeleteHandlers = [];
    }
    public function onRead(callable $onRead) : AdminTableHelperInterface
    {
        $this->onReadHandlers[] = $onRead;
        return $this;
    }
    public function onDelete(callable $onDelete) : AdminTableHelperInterface
    {
        $this->onDeleteHandlers[] = $onDelete;
        return $this;
    }
    public function getDescriptor() : array
    {
        return $this->parent;
    }
    public function addColumn(array $columnDescriptor) : AdminTableHelperInterface
    {
        $this->columnGroup["columns"][] = $columnDescriptor;
        if ($this->parent["key"] === null) {
            $this->parent["key"] = $columnDescriptor["id"];
        }
        return $this;
    }
    public function addColumnGroup(string $label = null, string $id = null, array $columns = []) : AdminTableHelperInterface
    {
        $groupDescriptor = static::createGroupDescriptorInstance($label, $id, $columns);
        if (array_key_exists("columnGroups", $this->parent) && (PHP::isArray($this->parent["columnGroups"]) && PHP::count($this->parent["columnGroups"]) === 1) && PHP::isArray($this->parent["columnGroups"][0]) && array_key_exists("columns", $this->parent["columnGroups"][0]) && (PHP::isArray($this->parent["columnGroups"][0]["columns"]) && PHP::count($this->parent["columnGroups"][0]["columns"]) === 0)) {
            $this->parent["columnGroups"][0] = $groupDescriptor;
        } else {
            $this->parent["columnGroups"][] = $groupDescriptor;
        }
        $this->columnGroup =& $this->parent["columnGroups"][count($this->parent["columnGroups"]) - 1];
        return $this;
    }
    public function processAndRender(bool $echo = true) : string
    {
        $this->process();
        return $this->render($echo);
    }
    public function process() : void
    {
        $state = ['delete' => filter_input(INPUT_GET, Constants::LIST_ACTION_QUERYSTRING_PARAMETER, FILTER_DEFAULT) === 'delete', 'record' => filter_input(INPUT_GET, 'record', FILTER_DEFAULT), 'records' => filter_input(INPUT_GET, 'records', FILTER_DEFAULT), 'list' => filter_input(INPUT_GET, 'list', FILTER_DEFAULT)];
        if ($state['delete'] === true && $this->parent['id'] === $state['list']) {
            foreach ($this->onDeleteHandlers as $handler) {
                if ($state['records'] !== null) {
                    $handler(explode(',', $state['records']), $this->parent['key']);
                } else {
                    if ($state['record'] !== null) {
                        $handler([$state['record']], $this->parent['key']);
                    }
                }
            }
            $tmp = parse_url(PHP::getServerRequestUri());
            $scheme = array_key_exists('scheme', $tmp) ? $tmp['scheme'] . '://' : '';
            $host = array_key_exists('host', $tmp) ? $tmp['host'] : '';
            $path = array_key_exists('path', $tmp) ? $tmp['path'] : '';
            $query = [];
            array_key_exists('query', $tmp) ? parse_str($tmp['query'], $query) : [];
            if (array_key_exists('list-action', $query)) {
                while (array_key_exists('list-action', $query)) {
                    unset($query['list-action']);
                }
                while (array_key_exists('record', $query)) {
                    unset($query['record']);
                }
                while (array_key_exists('key', $query)) {
                    unset($query['key']);
                }
                while (array_key_exists('list', $query)) {
                    unset($query['list']);
                }
            }
            $url = $scheme . $host . $path . (count($query) > 0 ? '?' . http_build_query($query) : '');
            WP::redirect($url);
        }
    }
    public function render(bool $echo = true) : string
    {
        $state = ['list' => filter_input(INPUT_GET, Constants::LIST_QUERYSTRING_PARAMETER, FILTER_DEFAULT), 'create' => filter_input(INPUT_GET, Constants::LIST_ACTION_QUERYSTRING_PARAMETER, FILTER_DEFAULT) === 'create', 'update' => filter_input(INPUT_GET, Constants::LIST_ACTION_QUERYSTRING_PARAMETER, FILTER_DEFAULT) === 'update', 'record' => filter_input(INPUT_GET, 'record', FILTER_DEFAULT)];
        ob_start();
        static::setDetailMode(true);
        if ($this->parent['detailView'] !== null) {
            $this->parent['detailView'](false);
        } else {
            echo 'TODO: generate default detail view';
        }
        static::setDetailMode(false);
        $detail = ob_get_clean();
        ob_start();
        if ($state['create'] === true || $state['update'] === true && $detail !== null && $state['list'] === $this->parent['id']) {
            echo $detail;
        } else {
            $descriptor = $this->parent;
            $values = [];
            if (PHP::count($this->onReadHandlers) === 0) {
                $this->readFromOptions($state['record']);
            }
            foreach ($this->onReadHandlers as $handler) {
                $values = $handler($values, $state['record'], $descriptor['key']);
            }
            $table = new WordPressTable($descriptor, $values);
            $table->display();
        }
        $output = ob_get_clean();
        if ($echo === true) {
            echo $output;
        }
        return $output;
    }
    public function readFromSqlTable(string $tableNameWithoutPrefix, array $where = null, string $tableNamePrefix = null) : AdminTableHelperInterface
    {
        $self = $this;
        global $wpdb;
        $table = ($tableNamePrefix === null ? $wpdb->prefix : $tableNamePrefix) . $tableNameWithoutPrefix;
        $columns = ['`' . $this->parent['key'] . '`'];
        foreach ($this->parent['columnGroups'] as $columnGroup) {
            foreach ($columnGroup['columns'] as $column) {
                $columns[] = '`' . $column['name'] . '`';
            }
        }
        $columnsString = join(', ', $columns);
        $whereString = '';
        if ($where !== null) {
            $conditions = [];
            foreach ($where as $field => $expression) {
                $expressions = [];
                if (is_array($expression) && count(array_keys($expression)) > 0) {
                    //FIXME: Should be PHP::isAssociativeArray()
                    foreach ($expression as $operator => $value) {
                        if (is_string($value)) {
                            $value = "'{$value}'";
                        }
                        $expressions[] = '`' . $field . '` ' . strtoupper($operator) . ' ' . $value;
                    }
                }
                $conditions[] = join(' OR ', $expressions);
            }
            if (count($conditions) > 0) {
                $whereString = ' WHERE ' . join(' AND ', $conditions);
            }
        }
        return $this->readFromSqlQuery(<<<SQL
SELECT {$columnsString} FROM `{$table}`{$whereString}
SQL
);
    }
    public function readFromSqlQuery(string $query) : AdminTableHelperInterface
    {
        return $this->onRead(function ($record, $key = null) use($query) {
            return WP::dbQuery($query);
        });
    }
    public function deleteFromSqlTable(string $tableNameWithoutPrefix, string $tableNamePrefix = null) : AdminTableHelperInterface
    {
        $self = $this;
        return $this->onDelete(function (array $items, $key) use($self, $tableNameWithoutPrefix, $tableNamePrefix) {
            global $wpdb;
            $table = ($tableNamePrefix === null ? $wpdb->prefix : $tableNamePrefix) . $tableNameWithoutPrefix;
            if ($key !== null && count($items) > 0) {
                $where = [];
                $values = [];
                foreach ($items as $item) {
                    $where[] = 'CAST(`' . $self->parent['key'] . '` AS CHAR(255)) LIKE (%s)';
                    $values[] = $wpdb->esc_like($item);
                }
                WP::dbQuery("DELETE FROM `{$table}` WHERE " . join(' OR ', $where), $values);
            }
        });
    }
    public function readFromOptions(string $optionName) : AdminTableHelperInterface
    {
        return $this->onRead(function ($record, $key) use($optionName) {
            $records = WP::getSiteOption($optionName);
            if ($records === null) {
                $records = [];
            }
            return array_values($records);
        });
    }
    public function deleteFromOptions(string $optionName) : AdminTableHelperInterface
    {
        return $this->onDelete(function (array $items, $key) use($optionName) {
            $records = WP::getSiteOption($optionName);
            if ($records === null) {
                $records = [];
            }
            foreach ($items as $index) {
                if (array_key_exists((string) $index, $records)) {
                    unset($records[(string) $index]);
                }
            }
            WP::setSiteOption($optionName, $records);
        });
    }
}