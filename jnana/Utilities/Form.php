<?php
namespace Jnana\Utilities;

class Form
{
    public static function input(string $name, string $type = 'text', array $attributes = [], mixed $model = null): string
    {
        $default = $model ? self::getModelValue($model, $name) : '';
        $value = old($name, $default);

        $attrString = '';
        foreach ($attributes as $k => $v) {
            $attrString .= " $k=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
        }

        return "<input type=\"$type\" name=\"$name\" value=\"$value\"$attrString>";
    }

    protected static function getModelValue(mixed $model, string $field): mixed
    {
        $method = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $field)));
        return method_exists($model, $method) ? $model->$method() : ($model->$field ?? '');
    }

    public static function textarea(string $name, array $attributes = [], mixed $model = null): string
    {
        $default = $model ? self::getModelValue($model, $name) : '';
        $value = old($name, $default);

        $attrString = '';
        foreach ($attributes as $k => $v) {
            $attrString .= " $k=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
        }

        return "<textarea name=\"$name\"$attrString>$value</textarea>";
    }

}
