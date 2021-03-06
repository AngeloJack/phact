<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 23/09/2018
 * Time: 23:13
 */
namespace Modules\Test\TemplateLibraries;

use Phact\Template\TemplateLibrary;

class TestLibrary extends TemplateLibrary
{
    /**
     * @name test_modifier
     * @kind modifier
     */
    public function testModifier($value)
    {
        return $value . '__TESTED';
    }

    /**
     * @name test_property
     * @kind accessorProperty
     */
    public function testProperty()
    {
        return "TEST_PROPERTY";
    }

    /**
     * @name test_accessor_function
     * @kind accessorFunction
     */
    public function testAccessorFunction($argument)
    {
        return "TEST_ACCESSOR_FUNCTION_WITH_ARGUMENT_" . $argument;
    }

    /**
     * @name test_function
     * @kind function
     */
    public function testFunction($argument)
    {
        return "TEST_FUNCTION_WITH_ARGUMENT_" . $argument[0];
    }
}