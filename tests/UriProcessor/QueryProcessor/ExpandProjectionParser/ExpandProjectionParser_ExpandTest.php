<?php
use ODataProducer\UriProcessor\QueryProcessor\ExpandProjectionParser\ExpandedProjectionNode;
use ODataProducer\Configuration\EntitySetRights;
use ODataProducer\Providers\MetadataQueryProviderWrapper;
use ODataProducer\Configuration\DataServiceConfiguration;
use ODataProducer\UriProcessor\QueryProcessor\ExpandProjectionParser\ExpandProjectionParser;
use ODataProducer\Common\ODataException;

require_once(dirname(__FILE__) . "/../../../Resources/NorthWindMetadata.php");
require_once(dirname(__FILE__) . "/../../../Resources/NorthWindQueryProvider.php");

class TestExpandProjectionParser_Expand extends PHPUnit_Framework_TestCase
{   
    protected function setUp()
    {
    }

    /**
     * Test case for testing empty expand and select clause     
     */
    public function testEmptyExpandAndSelect()
    {
        try { 
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType(); 
                //check with empty expand/select option 
                $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                            $customersResourceSetWrapper, 
                                                            $customerResourceType,
                                                            null, 
                                                            null, 
                                                            null, 
                                                            null, // $expand 
                                                            null, // $select 
                                                            $metaQueryProverWrapper);
                //The root of tree represents the details identifed by the request uri path
                //PropertyName and ResourceProperty must be null for root
                $this->assertNull($projectionTreeRoot->getPropertyName());
                $this->assertNull($projectionTreeRoot->getResourceProperty());
                $this->assertNotNull($projectionTreeRoot->getResourceSetWrapper());
                $this->assertNotNull($projectionTreeRoot->getResourceType());
                //There is no child node for root
                $this->assertEquals(count($projectionTreeRoot->getChildNodes()), 0);
                $this->assertEquals($projectionTreeRoot->getResourceSetWrapper()->getName(), $customersResourceSetWrapper->getName());
                $this->assertEquals($projectionTreeRoot->getResourceType()->getName(), $customerResourceType->getName());
                //Since no expansion and selection applied, the corrosponding flag will be false
                $this->assertFalse($projectionTreeRoot->isExpansionSpecified());
                $this->assertFalse($projectionTreeRoot->isSelectionSpecified());
                //No selection means we need to return all properties of the resource type identifed by the request uri path
                //selectsubtree flag should be true
                $this->assertTrue($projectionTreeRoot->canSelectAllProperties());
                //flag for SelectionOfImmediate properties will be true only if select include '*'
                $this->assertFalse($projectionTreeRoot->canSelectAllImmediateProperties());
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * Test expand with only one level of navigation     
     */
    public function testExpandWithOneLevelNavigationProperty()
    {
            try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType(); 
                 
                $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                            $customersResourceSetWrapper, 
                                                            $customerResourceType, 
                                                            null,
                                                            null, 
                                                            null, 
                                                            'Orders', // $expand 
                                                            null, // $select 
                                                            $metaQueryProverWrapper);
                //Expansion is specified but selection is absent
                $this->assertTrue($projectionTreeRoot->isExpansionSpecified());
                $this->assertFalse($projectionTreeRoot->isSelectionSpecified());
                //There is one child node for root
                $this->assertEquals(count($projectionTreeRoot->getChildNodes()), 1);
                $childNodes = $projectionTreeRoot->getChildNodes();
                //The child node (of type ExpandedProjectionNode) represents 'Orders' navigation property
                $this->assertTrue(array_key_exists('Orders', $childNodes));
                $this->assertTrue($childNodes['Orders'] instanceof ExpandedProjectionNode);
                //Check the required fields of the node is populated with proper values
                $this->assertNotNull($childNodes['Orders']->getPropertyName());
                $this->assertEquals($childNodes['Orders']->getPropertyName(), 'Orders');
                $this->assertNotNull($childNodes['Orders']->getResourceProperty());
                $this->assertEquals($childNodes['Orders']->getResourceProperty()->getName(), 'Orders');
                $this->assertNotNull($childNodes['Orders']->getResourceSetWrapper());
                $this->assertNotNull($childNodes['Orders']->getResourceSetWrapper()->getName(), 'Orders');
                $this->assertNotNull($childNodes['Orders']->getResourceType());
                $this->assertNotNull($childNodes['Orders']->getResourceType()->getName(), 'Order');
                //We didn't applied any selection, so all properties needs to be selected by default
                //this one actually test whether 'ExpandedProjectionNode::markSubTreeAsSelected' method works properly
                $this->assertTrue($projectionTreeRoot->canSelectAllProperties());                
                $this->assertTrue($childNodes['Orders']->canSelectAllProperties());
                //flag for SelectionOfImmediate properties will be true only if select include 'Orders\*'
                $this->assertFalse($childNodes['Orders']->canSelectAllImmediateProperties());
                
                
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * Test expand with only one level of navigation with duplication     
     */
    public function testExpandWithOneLevelNavigationPropertyWithDuplication()
    {
            try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType();
                $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                            $customersResourceSetWrapper, 
                                                            $customerResourceType,
                                                            null, 
                                                            null, 
                                                            null, 
                                                            'Orders,Orders', // $expand 
                                                            null, // $select 
                                                            $metaQueryProverWrapper);
                //Expansion is specified but selection is absent
                $this->assertTrue($projectionTreeRoot->isExpansionSpecified());
                $this->assertFalse($projectionTreeRoot->isSelectionSpecified());
                //There is one child node for root
                $this->assertEquals(count($projectionTreeRoot->getChildNodes()), 1);
                $childNodes = $projectionTreeRoot->getChildNodes();
                //The child node (of type ExpandedProjectionNode) represents 'Orders' navigation property
                $this->assertTrue(array_key_exists('Orders', $childNodes));
                $this->assertTrue($childNodes['Orders'] instanceof ExpandedProjectionNode);
                //We didn't applied any selection, so all properties needs to be selected by default
                //this one actually test whether 'ExpandedProjectionNode::markSubTreeAsSelected' method works properly
                $this->assertTrue($projectionTreeRoot->canSelectAllProperties());                
                $this->assertTrue($childNodes['Orders']->canSelectAllProperties());
                //flag for SelectionOfImmediate properties will be true only if select include 'Orders\*'
                $this->assertFalse($childNodes['Orders']->canSelectAllImmediateProperties());
                
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * Test expand with non-identifers in the path     
     */
    public function testExpandWithNonIdentiferPathSegment()
    {
                try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType();
                $exceptionThrown = false;
                try {
                    $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType,
                                                                null, 
                                                                null, 
                                                                null, 
                                                                'Orders,123', // $expand 
                                                                null,         // $select 
                                                                $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringStartsWith('Syntax Error at position', $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for syntax error has not been thrown');
                }
                
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }
    
    /**
     * Test expand path that start with comma and end with comma
     */
    public function testExpandWithStartEndTokenAsComma()
    {
                try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType();
                $exceptionThrown = false;
                try {
                    $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType,                                                                
                                                                null, 
                                                                null, 
                                                                null, 
                                                                ',Orders', // $expand 
                                                                null,         // $select 
                                                                $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringStartsWith('Syntax Error at position', $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for syntax error has not been thrown');
                }

                $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType, 
                                                                null, 
                                                                null,
                                                                null, 
                                                                'Orders,', // $expand 
                                                                null,         // $select 
                                                                $metaQueryProverWrapper);

                $exceptionThrown = false;
                try {
                    $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType, 
                                                                null, 
                                                                null, 
                                                                null,
                                                                'Orders,,', // $expand 
                                                                null,       // $select 
                                                                $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringStartsWith('Syntax Error at position', $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for syntax error has not been thrown');
                }
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * Test expand with non-navigation properties in the path
     */
    public function testExpandWithNonNavigationPropertyInThePath()
    {
                try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType();
                $exceptionThrown = false;
                try {
                    //Test with Primitive property in expand path
                    $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType, 
                                                                null, 
                                                                null, 
                                                                null,
                                                                'CustomerName', // $expand 
                                                                null,         // $select 
                                                                $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringStartsWith("Error in the expand clause. Expand path can contain only navigation property, the property 'CustomerName' defined in 'Customer' is not a navigation property", $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for non-navigation property in the path has not been thrown');
                }

                                $exceptionThrown = false;
                try {
                    //Test with complex property in expand path
                    $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType, 
                                                                null, 
                                                                null, 
                                                                null,
                                                                'Address', // $expand 
                                                                null,         // $select 
                                                                $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringStartsWith("Error in the expand clause. Expand path can contain only navigation property", $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for non-navigation property in the path has not been thrown');
                }
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * '*' token will only work with select clause, with expand its syntax error     
     */
    public function testExpandWithSelectAllTokenInThePath()
    {
                try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType();
                $exceptionThrown = false;
                try {
                    //Test with * in expand path
                    $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                                $customersResourceSetWrapper, 
                                                                $customerResourceType, 
                                                                null, 
                                                                null, 
                                                                null,
                                                                '*', // $expand 
                                                                null,         // $select 
                                                                $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringStartsWith("Syntax Error at position", $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for non-navigation property in the path has not been thrown');
                }
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * Test expand with single path segment with multiple sub path segments     
     */
    public function testExpandWithMultilevelNavigationProperty()
    {
                try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType(); 
                 
                $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                            $customersResourceSetWrapper, 
                                                            $customerResourceType, 
                                                            null, 
                                                            null, 
                                                            null,
                                                            'Orders/Order_Details/Product', // $expand 
                                                            null, // $select 
                                                            $metaQueryProverWrapper);
                //Expansion is specified but selection is absent
                $this->assertTrue($projectionTreeRoot->isExpansionSpecified());
                $this->assertFalse($projectionTreeRoot->isSelectionSpecified());
                
                //There is one child node for root
                $this->assertEquals(count($projectionTreeRoot->getChildNodes()), 1);
                $childNodes = $projectionTreeRoot->getChildNodes();
                //The child node (of type ExpandedProjectionNode) represents 'Orders' navigation property
                $this->assertTrue(array_key_exists('Orders', $childNodes));
                $this->assertTrue($childNodes['Orders'] instanceof ExpandedProjectionNode);                
                //Check the required fields of the node is populated with proper values
                $this->assertNotNull($childNodes['Orders']->getPropertyName());
                $this->assertEquals($childNodes['Orders']->getPropertyName(), 'Orders');
                $this->assertNotNull($childNodes['Orders']->getResourceProperty());
                $this->assertEquals($childNodes['Orders']->getResourceProperty()->getName(), 'Orders');
                $this->assertNotNull($childNodes['Orders']->getResourceSetWrapper());
                $this->assertNotNull($childNodes['Orders']->getResourceSetWrapper()->getName(), 'Orders');
                $this->assertNotNull($childNodes['Orders']->getResourceType());
                $this->assertNotNull($childNodes['Orders']->getResourceType()->getName(), 'Order');
                //We didn't applied any selection, so all properties needs to be selected by default                
                $this->assertTrue($projectionTreeRoot->canSelectAllProperties());                
                $this->assertTrue($childNodes['Orders']->canSelectAllProperties());
                //flag for SelectionOfImmediate properties will be true only if select include 'Orders\*'
                $this->assertFalse($childNodes['Orders']->canSelectAllImmediateProperties());

                //Now get next level child nodes i.e. Order_details from Orders
                $this->assertEquals(count($childNodes['Orders']->getChildNodes()), 1);
                $childNodes = $childNodes['Orders']->getChildNodes();
                $this->assertTrue(array_key_exists('Order_Details', $childNodes));                
                $this->assertTrue($childNodes['Order_Details'] instanceof ExpandedProjectionNode);
                //Check the required fields of the node is populated with proper values
                $this->assertNotNull($childNodes['Order_Details']->getPropertyName());
                $this->assertEquals($childNodes['Order_Details']->getPropertyName(), 'Order_Details');
                $this->assertNotNull($childNodes['Order_Details']->getResourceProperty());
                $this->assertEquals($childNodes['Order_Details']->getResourceProperty()->getName(), 'Order_Details');
                $this->assertNotNull($childNodes['Order_Details']->getResourceSetWrapper());
                $this->assertNotNull($childNodes['Order_Details']->getResourceSetWrapper()->getName(), 'Order_Details');
                $this->assertNotNull($childNodes['Order_Details']->getResourceType());
                $this->assertNotNull($childNodes['Order_Details']->getResourceType()->getName(), 'Order_Detail');
                //We didn't applied any selection, so all properties needs to be selected by default
                $this->assertTrue($childNodes['Order_Details']->canSelectAllProperties());
                //flag for SelectionOfImmediate properties will be true only if select include 'Orders/Order_Details/*'
                $this->assertFalse($childNodes['Order_Details']->canSelectAllImmediateProperties());

                //Now get next level child nodes i.e. Product from Order_Details
                $this->assertEquals(count($childNodes['Order_Details']->getChildNodes()), 1);
                $childNodes = $childNodes['Order_Details']->getChildNodes();
                $this->assertTrue(array_key_exists('Product', $childNodes));                
                $this->assertTrue($childNodes['Product'] instanceof ExpandedProjectionNode);
                //Check the required fields of the node is populated with proper values
                $this->assertNotNull($childNodes['Product']->getPropertyName());
                $this->assertEquals($childNodes['Product']->getPropertyName(), 'Product');
                $this->assertNotNull($childNodes['Product']->getResourceProperty());
                $this->assertEquals($childNodes['Product']->getResourceProperty()->getName(), 'Product');
                $this->assertNotNull($childNodes['Product']->getResourceSetWrapper());
                $this->assertNotNull($childNodes['Product']->getResourceSetWrapper()->getName(), 'Products');
                $this->assertNotNull($childNodes['Product']->getResourceType());
                $this->assertNotNull($childNodes['Product']->getResourceType()->getName(), 'Product');
                //We didn't applied any selection, so all properties needs to be selected by default
                $this->assertTrue($childNodes['Product']->canSelectAllProperties());
                //flag for SelectionOfImmediate properties will be true only if select include 'Orders/Order_Details/Product/*'
                $this->assertFalse($childNodes['Product']->canSelectAllImmediateProperties());
                
                //no more navigation
                $this->assertEquals(count($childNodes['Product']->getChildNodes()), 0);
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * Test expand with multiple path segment with multiple sub path segments     
     */
    public function testExpandWithMultipleMultilevelNavigationProperty()
    {
        try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                               $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                               $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                               $configuration, //Service configuration
                                               false
                                             );
                $orderDetailsResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Order_Details');
                $orderDetailResourceType = $orderDetailsResourceSetWrapper->getResourceType(); 
                 
                $projectionTreeRoot = ExpandProjectionParser::parseExpandAndSelectClause(
                                                            $orderDetailsResourceSetWrapper, 
                                                            $orderDetailResourceType, 
                                                            null, 
                                                            null, 
                                                            null,
                                                            'Order/Customer, Product/Order_Details', // $expand 
                                                            null, // $select 
                                                            $metaQueryProverWrapper);
                //Expansion is specified but selection is absent
                $this->assertTrue($projectionTreeRoot->isExpansionSpecified());
                $this->assertFalse($projectionTreeRoot->isSelectionSpecified());

                //There are two child node for root
                $this->assertEquals(count($projectionTreeRoot->getChildNodes()), 2);
                $childNodes = $projectionTreeRoot->getChildNodes();
                //One child node (of type ExpandedProjectionNode) represents 'Order' navigation property
                $this->assertTrue(array_key_exists('Order', $childNodes));
                $this->assertTrue($childNodes['Order'] instanceof ExpandedProjectionNode);
                //second child node (of type ExpandedProjectionNode) represents 'Product' navigation property
                $this->assertTrue(array_key_exists('Product', $childNodes));
                $this->assertTrue($childNodes['Product'] instanceof ExpandedProjectionNode);
                
                //'Order' has next level navigation property
                $childNodesOfOrder = $childNodes['Order']->getChildNodes();
                $this->assertEquals(count($childNodesOfOrder), 1);
                $this->assertTrue(array_key_exists('Customer', $childNodesOfOrder));
                $this->assertTrue($childNodesOfOrder['Customer'] instanceof ExpandedProjectionNode);
                
                //'Product' has next level navigation Property
                $childNodesOfProduct = $childNodes['Product']->getChildNodes();
                $this->assertEquals(count($childNodesOfProduct), 1);
                $this->assertTrue(array_key_exists('Order_Details', $childNodesOfProduct));
                $this->assertTrue($childNodesOfProduct['Order_Details'] instanceof ExpandedProjectionNode);
                
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    /**
     * One can expand a navigation property only if corrosponding resource set is visible
     * 
     */
    public function testExpandWithNonVisibleResourceSet()
    {
        try {
                $northWindMetadata = CreateNorthWindMetadata3::Create();
                $queryProvider = new NorthWindQueryProvider2();
                $configuration = new DataServiceConfiguration($northWindMetadata);
                //Make 'Customers' and 'Orders' visible, make 'Order_Details' invisible
                $configuration->setEntitySetAccessRule('Customers', EntitySetRights::ALL);
                $configuration->setEntitySetAccessRule('Orders', EntitySetRights::ALL);
                $metaQueryProverWrapper = new MetadataQueryProviderWrapper(
                                                  $northWindMetadata, //IDataServiceMetadataProvider implementation 
                                                  $queryProvider, //IDataServiceQueryProvider implementation (set to null)
                                                  $configuration, //Service configuuration
                                                  false
                                                 );

                $customersResourceSetWrapper = $metaQueryProverWrapper->resolveResourceSet('Customers');
                $customerResourceType = $customersResourceSetWrapper->getResourceType(); 

                $exceptionThrown = false;
                try {                   
                
                    $projectionTree = ExpandProjectionParser::parseExpandAndSelectClause(
                            $customersResourceSetWrapper, 
                            $customerResourceType, 
                            null,
                            null,
                            null,
                        	'Orders/Order_Details', // $expand 
                            null,     // $select 
                            $metaQueryProverWrapper);
                } catch (ODataException $odataException) {
                    $exceptionThrown = true;
                    $this->assertStringEndsWith("(Check the resource set of the navigation property 'Order_Details' is visible)", $odataException->getMessage());
                }

                if (!$exceptionThrown) {
                    $this->fail('An expected ODataException for navigation to invisible resource set has not been thrown');
                }
                
        } catch (\Exception $exception) {
            $this->fail('An unexpected Exception has been raised' . $exception->getMessage());
        }
    }

    protected function tearDown()
    {
    }
}
?>