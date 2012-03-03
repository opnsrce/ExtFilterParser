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

namespace OpnSrce\Ext\ExtFilterParser\Tests;

use OpnSrce\Ext\ExtFilterParser\ExtFilterParser;

/**
 * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser
 */
class ExtFilterParserTest extends \PHPUnit_Framework_TestCase {

    private $extFilterParser;

    public function requestMockDataProvider() {
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

    public function queryBuilderDataProvider() {
        $queryBuilder = $this->getMock('\Doctrine\ORM\QueryBuilder', array('andWhere'));
        $queryBuilder
                ->expects($this->any())
                ->method('andWhere')
                ->will($this->returnSelf());
        return array(
            array($queryBuilder)
        );
    }

    public function dateDataProvider() {
        return array(
            array('2011-06-30', 'Y-m-d'),
            array('June 30, 2011', 'F d, Y'),
            array('06/30/2011', 'm/d/Y')
        );
    }

    public function setUp() {
        $this->extFilterParser = new ExtFilterParser();
    }

    /**
     * @param string $type The filter type to generate
     * @param string $field The field the filter applies to
     * @param mixed $value The value to filter by
     * @param string $comparison (Optional) Comparison Operator
     */
    public function generateFilterJson($type, $field, $value, $comparison = '') {
        return json_encode(array('type' => $type, 'field' => $field, 'value' => $value, 'comparison' => $comparison));
    }

    /**
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::__construct
     */
    public function testForProperties() {
        $className = get_class($this->extFilterParser);
        $this->assertClassHasAttribute('parsedFilters', $className);
        $this->assertClassHasAttribute('filters', $className);
        $this->assertClassHasAttribute('requestParam', $className);
        $this->assertClassHasAttribute('dateFormat', $className);
    }

    /**
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::__construct
     */
    public function testPropertyDefaultValues() {
        $extFilterParser = new ExtFilterParser();
        $this->assertAttributeEmpty('parsedFilters', $extFilterParser, 'ExtFilterParser::parsedFilters should default to an empty array');
        $this->assertAttributeEquals('filter', 'requestParam', $extFilterParser, 'ExtFilterParser::requestParam should default to \'filter\'');
        $this->assertAttributeEquals('Y-m-d', 'dateFormat', $extFilterParser, 'ExtFilterParser::dateFormat should default to \'Y-m-d\'');
        $this->assertAttributeEquals('', 'filters', $extFilterParser, 'ExtFilterParser::filters should default to an empty string');
    }

    /**
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::__construct
     */
    public function testPropertyDataTypes() {
        $this->assertAttributeInternalType('array', 'parsedFilters', $this->extFilterParser);
        $this->assertAttributeInternalType('string', 'dateFormat', $this->extFilterParser);
        $this->assertAttributeInternalType('string', 'filters', $this->extFilterParser);
        $this->assertAttributeInternalType('string', 'requestParam', $this->extFilterParser);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ExtFilterParser::decodeFilterJson Expects first parameter to be a valid JSON string
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::decodeFilterJson
     */
    public function testParseBadJsonException() {
        $this->extFilterParser->setFilters('bad json')->parse();
    }

    /**
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::__toString
     */
    public function test__toString() {
        $filterJson = $this->generateFilterJson('string', 'firstName', 'Steve');
        $this->extFilterParser->setFilters($filterJson);
        $objectAsString = "$this->extFilterParser";
        $this->assertEquals($filterJson, $objectAsString);
    }

    /**
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::__construct
     */
    public function testSetValuesFromConstructor() {
        $extFilterParser = new ExtFilterParser('extFilter', 'm/d/y');
        $this->assertAttributeEquals('extFilter', 'requestParam', $extFilterParser, 'ExtFilterParser::requestParam not getting set in constructor');
        $this->assertAttributeEquals('m/d/y', 'dateFormat', $extFilterParser, 'ExtFilterParser::dateFormat not getting set in constructor');
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ExtFilterParser::translateComparisonOperator Invalid comparison operator 'bad'
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::decodeFilterJson
     */
    public function testParseUnknownComparisonOperatorException() {
        $this->extFilterParser->setFilters('{"type":"numeric", "field": "foo", "value":"bar", "comparison": "bad"}')->parse();
    }

    /**
     *
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ExtFilterParser::parse Unknown filter type 'bad'
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::decodeFilterJson
     */
    public function testParseUnknownFilterTypeException() {
        $this->extFilterParser->setFilters('{"type":"bad", "field": "foo", "value":"1"}')->parse();
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::pullFiltersFromGetOrPost
     */
    public function testPullFiltersFromGet() {
        $_GET['filter'] = $this->generateFilterJson('string', 'stringField', 'A');
        $this->extFilterParser = new ExtFilterParser();
        $parsedFilter = $this->extFilterParser->parse()->getParsedFilters();
        $this->assertEquals("stringField LIKE", $parsedFilter[0]['expression']);
        $this->assertEquals("'%A%'", $parsedFilter[0]['value']);
        $this->testParseStringFilter();
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::pullFiltersFromGetOrPost
     */
    public function testPullFiltersFromPost() {
        $_POST['filter'] = $this->generateFilterJson('string', 'stringField', 'A');
        $this->extFilterParser = new ExtFilterParser();
        $parsedFilter = $this->extFilterParser->parse()->getParsedFilters();
        $this->assertEquals("stringField LIKE", $parsedFilter[0]['expression']);
        $this->assertEquals("'%A%'", $parsedFilter[0]['value']);
        $this->testParseStringFilter();
    }
    /**
     *
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage ExtFilterParser::parse Unknown filter type 'badValue'
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     */
    public function testParseUnexpectedFilterTypeException() {
        $this->extFilterParser->setFilters('{"type":"badValue", "field": "foo", "value":"1"}')->parse();
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::decodeFilterJson
     */
    public function testParseStringFilter() {
        $filter = $this->generateFilterJson('string', 'stringField', 'A');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertEquals("stringField LIKE", $parsedFilter[0]['expression']);
        $this->assertEquals("'%A%'", $parsedFilter[0]['value']);
    }

    /**
     *
     * @dataProvider dateDataProvider
     * @param string $date The date to be parsed
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseDateFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setDateFormat
     */
    public function testParseDateFilter($date, $dateFormat) {
        $this->extFilterParser->setDateFormat($dateFormat);

        $filter = $this->generateFilterJson('date', 'dateField', $date, 'lt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $formattedDate = date($dateFormat, strtotime($date));

        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("dateField <", $parsedFilter[0]['expression']);
        $this->assertEquals("'$formattedDate'", $parsedFilter[0]['value']);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseDateFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setDateFormat
     */
    public function testParseDateFilterEmptyDate() {
        $filter = $this->generateFilterJson('date', 'dateField', '0000-00-00', 'gt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("dateField >", $parsedFilter[0]['expression']);
        $this->assertEquals("''", $parsedFilter[0]['value']);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseComparisonFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     */
    public function testParseNumericFilterLessThan() {
        $filter = $this->generateFilterJson('numeric', 'numericField', 1, 'lt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("numericField <", $parsedFilter[0]['expression']);
        $this->assertEquals("1", $parsedFilter[0]['value']);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseComparisonFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     */
    public function testParseNumericFilterGreaterThan() {
        $filter = $this->generateFilterJson('numeric', 'numericField', 1, 'gt');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("numericField >", $parsedFilter[0]['expression']);
        $this->assertEquals("1", $parsedFilter[0]['value']);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseComparisonFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     */
    public function testParseNumericFilterEqualTo() {
        $filter = $this->generateFilterJson('numeric', 'numericField', 1, 'eq');
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("numericField =", $parsedFilter[0]['expression']);
        $this->assertEquals("1", $parsedFilter[0]['value']);
    }

    /**
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseListFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     */
    public function testParseListFilterAsArray() {
        $filter = $this->generateFilterJson('list', 'listField', array('a', 'b', 'c'));
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("listField IN", $parsedFilter[0]['expression']);
        $this->assertEquals("('a','b','c')", $parsedFilter[0]['value']);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parseListFilter
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     */
    public function testParseListFilterAsCsv() {
        $filter = $this->generateFilterJson('list', 'listField', "a,b,c");
        $parsedFilter = $this->extFilterParser->setFilters($filter)->parse()->getParsedFilters();
        $this->assertCount(1, $parsedFilter);
        $this->assertEquals("listField IN", $parsedFilter[0]['expression']);
        $this->assertEquals("('a','b','c')", $parsedFilter[0]['value']);
    }

    /*
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::setFilters
     */

    public function testSetFiltersReturnsInstance() {
        $instance = $this->extFilterParser->setFilters($this->generateFilterJson('list', 'listField', "a,b,c"));
        $this->assertInstanceOf(get_class($this->extFilterParser), $instance);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::parse
     */
    public function testParseReturnsInstance() {
        $instance = $this->extFilterParser->setFilters($this->generateFilterJson('list', 'listField', "a,b,c"))->parse();
        $this->assertInstanceOf(get_class($this->extFilterParser), $instance);
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony::parseIntoQuery
     */
    public function testParseIntoQuery() {
        $filter = $this->generateFilterJson('date', 'dateField', '2010-10-10', 'lt');
        $expectedResult = "SELECT * FROM myTable WHERE dateField < '2010-10-10'";

        $this->extFilterParser->setFilters($filter);
        $this->assertEquals($expectedResult, $this->extFilterParser->parseIntoQuery('SELECT * FROM myTable'));
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getFilters
     */
    public function testGetFilters() {
        $filter = $this->generateFilterJson('date', 'dateField', '2010-10-10', 'lt');
        $this->extFilterParser->setFilters($filter);
        $this->assertEquals($filter, $this->extFilterParser->getFilters());
    }

    /**
     *
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParser::getDateFormat
     */
    public function testGetDateFormat() {
        $extFilterParser = new ExtFilterParser('filter', 'm/d/y');
        $this->assertEquals('m/d/y', $extFilterParser->getDateFormat());
    }

}