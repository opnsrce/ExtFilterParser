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

use OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony;

/**
 * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony
 */
class ExtFilterParserSymfonyTest extends \PHPUnit_Framework_TestCase {

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

    public function setUp() {
        $this->extFilterParser = new ExtFilterParserSymfony();
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
     *
     * @dataProvider requestMockDataProvider
     * @param \Symfony\Component\HttpFoundation\Request Instance of the Symfony2 Request Object
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony::getParsedFilters
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony::parse
     * @covers OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony::pullFiltersFromGetOrPost
     */
    public function testPullFiltersFromGetOrPost(\Symfony\Component\HttpFoundation\Request $request) {
        $this->extFilterParser = new ExtFilterParserSymfony($request);
        $parsedFilter = $this->extFilterParser->parse()->getParsedFilters();
        $this->assertEquals("stringField LIKE", $parsedFilter[0]['expression']);
        $this->assertEquals("'%A%'", $parsedFilter[0]['value']);
    }
    
    /**
     *
     * @dataProvider queryBuilderDataProvider
     * @param \Doctrine\ORM\QueryBuilder $query_builder
     */
    public function testParseIntoQuery($query_builder) {
        $filter = $this->generateFilterJson('date', 'dateField', '2010-10-10', 'lt');
        $this->extFilterParser->setFilters($filter);
        $this->assertInstanceOf('\Doctrine\ORM\QueryBuilder', $this->extFilterParser->parseIntoQuery($query_builder));
    }
}