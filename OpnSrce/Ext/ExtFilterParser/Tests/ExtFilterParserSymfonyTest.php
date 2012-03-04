<?php

namespace OpnSrce\Ext\ExtFilterParser\Tests;

use OpnSrce\Ext\ExtFilterParser\ExtFilterParserSymfony;

/**
 * Unit tests for the Symfony extension of ExtFilterParser
 *
 * @package Opnsrce\Ext\ExtFilterParser
 * @subpackage Tests
 * @namespace \Opnsrce\Ext\ExtFilterParser\Tests
 * @author Levi Hackwith <levi.hackwith@gmail.com>
 * @copyright 2012 Levi Hackwith
 * @license http://opensource.org/licenses/mit-license.php MIT License
 *
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