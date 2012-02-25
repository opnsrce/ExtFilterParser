<?php
/**
 * Copyright (c) 2011 Levi Hackwith <levi.hackwith@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Ext\ExtFilterParser\Tests;

use Ext\ExtFilterParser\ExtFilterParser;

class ExtFilterParserTest extends \PHPUnit_Framework_TestCase
{

    private $extFilterParser;

    public function requestMockDataProvider()
    {
        $testFilterJson = $this->generateFilterJson('string', 'stringField', 'A');
        $returnData = array();

        $requestMockForGet = $this->getMock('\Symfony\Component\HttpFoundation\Request');
        $requestMockForPost = $this->getMock('\Symfony\Component\HttpFoundation\Request');
        $paramBagMock = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag', array('get'));
        $paramBagMockEmpty = clone $paramBagMock;
        $paramBagMock
                ->expects($this->any())->method('get')
                ->will($this->returnValue($testFilterJson));

        $paramBagMockEmpty
                ->expects($this->any())
                ->method('get')
                ->will($this->returnValue(''));

        $requestMockForGet->request = $paramBagMockEmpty; // Only test for Data in $_GET
        $requestMockForGet->query = $paramBagMock;
        $returnData[] = array($requestMockForGet);


        $requestMockForPost->request = $paramBagMock;
        $requestMockForPost->query = $paramBagMockEmpty; // Only test for Data in $_POST
        $returnData[] = array($requestMockForPost);

        return $returnData;
    }

    public function queryBuilderDataProvider()
    {
        $queryBuilder = $this->getMock('\Doctrine\ORM\QueryBuilder', array('andWhere'));
        $queryBuilder
                ->expects($this->any())
                ->method('andWhere')
                ->will($this->returnSelf());
        return array(
            array($queryBuilder)
        );
    }

    public function dateDataProvider()
    {
        return array(
            array(
                '2011-06-30',
                'June 30, 2011',
                '06/30/2011',
                '30/06/2011'
            )
        );
    }

    public function setUp()
    {
        $this->extFilterParser = new ExtFilterParser();
    }

    /**
     * @param string $type The filter type to generate
     * @param string $field The field the filter applies to
     * @param mixed $value The value to filter by
     * @param string $comparison (Optional) Comparison Operator
     */
    public function generateFilterJson($type, $field, $value, $comparison = '')
    {
        return json_encode(array('type' => $type, 'field' => $field, 'value' => $value, 'comparison' => $comparison));
    }

    public function testForProperties()
    {
        $className = get_class($this->extFilterParser);
        $this->assertClassHasAttribute('parsedFilters', $className);
        $this->assertClassHasAttribute('filters', $className);
        $this->assertClassHasAttribute('requestParam', $className);
    }

    public function testPropertyDefaultValues()
    {
        $extFilterParser = new ExtFilterParser();
        $this->assertAttributeEmpty('parsedFilters', $extFilterParser, 'ExtFilterParser::parsedFilters should default to an empty array');
        $this->assertAttributeEquals('filter', 'requestParam', $extFilterParser, 'ExtFilterParser::requestParam should default to \'filter\'');
        $this->assertAttributeEquals('', 'filters', $extFilterParser, 'ExtFilterParser::filters should default to an empty string');
    }

    public function testPropertyDataTypes()
    {
        $this->assertAttributeInternalType('array', 'parsedFilters', $this->extFilterParser);
        $this->assertAttributeInternalType('string', 'filters', $this->extFilterParser);
        $this->assertAttributeInternalType('string', 'requestParam', $this->extFilterParser);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ExtFilterParser::decodeFilterJson Expects first parameter to be a valid JSON string
     */
    public function testParseBadJsonException()
    {
        $this->extFilterParser->setFilters('bad json')->parse();
    }

    public function test__toString()
    {
        $filterJson = $this->generateFilterJson('string', 'firstName', 'Steve');
        $this->extFilterParser->setFilters($filterJson);
        $objectAsString = "$this->extFilterParser";
        $this->assertEquals($filterJson, $objectAsString);
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ExtFilterParser::translateComparisonOperator Invalid comparison operator 'bad'
     */
    public function testParseUnknownComparisonOperatorException()
    {
        $this->extFilterParser->setFilters('{"type":"numeric", "field": "foo", "value":"bar", "comparison": "bad"}')->parse();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ExtFilterParser::parse Unknown filter type 'bad'
     */
    public function testParseUnknownFilterTypeException()
    {
        $this->extFilterParser->setFilters('{"type":"bad", "field": "foo", "value":"1"}')->parse();
    }

    /**
     * @dataProvider requestMockDataProvider
     * @param \Symfony\Component\HttpFoundation\Request Instance of the Symfony2 Request Object
     */
    public function testPullFilterJsonFromRequest(\Symfony\Component\HttpFoundation\Request $request)
    {
        $this->extFilterParser = new ExtFilterParser($request);
        $parsedFilter = $this->extFilterParser->parse()->getParsedFilters();
        $this->assertEquals("stringField LIKE", $parsedFilter[0]['expression']);
        $this->assertEquals("'%A%'", $parsedFilter[0]['value']);
        $this->testParseStringFilter();
    }

    /*
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ExtFilterParser::setFilters Unknown filter type 'badValue'
     */

    public function testParseUnexpectedFilterTypeException()
    {
        $this->extFilterParser->setFilters('{"type":"badValue", "field": "foo", "value":"1"}');
    }

    public function testParseStringFilter()
    {
        $filter = $this->generateFilterJson('string', 'stringField', 'A');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertEquals("stringField LIKE", $parsedFilter[0]['expression']);
        $this->assertEquals("'%A%'", $parsedFilter[0]['value']);
    }

    /**
     * @dataProvider dateDataProvider
     * @param string $date The date to be parsed
     */
    public function testParseDateFilter($date)
    {
        $filter = $this->generateFilterJson('date', 'dateField', $date, 'lt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("dateField <", $parsedFilter[0]['expression']);
        $this->assertEquals("'2011-06-30'", $parsedFilter[0]['value']);
    }

    public function testParseDateFilterEmptyDate()
    {
        $filter = $this->generateFilterJson('date', 'dateField', '0000-00-00', 'gt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("dateField >", $parsedFilter[0]['expression']);
        $this->assertEquals("''", $parsedFilter[0]['value']);
    }

    public function testParseNumericFilterLessThan()
    {
        $filter = $this->generateFilterJson('numeric', 'numericField', 1, 'lt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("numericField <", $parsedFilter[0]['expression']);
        $this->assertEquals("1", $parsedFilter[0]['value']);
    }

    public function testParseNumericFilterGreaterThan()
    {
        $filter = $this->generateFilterJson('numeric', 'numericField', 1, 'gt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("numericField >", $parsedFilter[0]['expression']);
        $this->assertEquals("1", $parsedFilter[0]['value']);
    }

    public function testParseNumericFilterEqualTo()
    {
        $filter = $this->generateFilterJson('numeric', 'numericField', 1, 'eq');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("numericField =", $parsedFilter[0]['expression']);
        $this->assertEquals("1", $parsedFilter[0]['value']);
    }

    public function testParseListFilterAsArray()
    {
        $filter = $this->generateFilterJson('list', 'listField', array('a', 'b', 'c'));
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("listField IN", $parsedFilter[0]['expression']);
        $this->assertEquals("('a','b','c')", $parsedFilter[0]['value']);
    }

    public function testParseListFilterAsCsv()
    {
        $filter = $this->generateFilterJson('list', 'listField', "a,b,c");
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("listField IN", $parsedFilter[0]['expression']);
        $this->assertEquals("('a','b','c')", $parsedFilter[0]['value']);
    }

    public function testSetFiltersReturnsInstance()
    {
        $instance = $this->extFilterParser->setFilters($this->generateFilterJson('list', 'listField', "a,b,c"));
        $this->assertInstanceOf(get_class($this->extFilterParser), $instance);
    }

    public function testParseReturnsInstance()
    {
        $instance = $this->extFilterParser->setFilters($this->generateFilterJson('list', 'listField', "a,b,c"))->parse();
        $this->assertInstanceOf(get_class($this->extFilterParser), $instance);
    }

    /**
     * @dataProvider queryBuilderDataProvider
     * @param \Doctrine\ORM\QueryBuilder $query_builder
     */
    public function testParseIntoQueryBuilder($query_builder)
    {
        $filter = $this->generateFilterJson('date', 'dateField', '2010-10-10', 'lt');
        $this->extFilterParser->setFilters($filter);
        $this->assertInstanceOf('\Doctrine\ORM\QueryBuilder', $this->extFilterParser->parseIntoQueryBuilder($query_builder));
    }

}