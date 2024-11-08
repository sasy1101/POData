<?php

namespace POData\Providers\Metadata;

use POData\Providers\Metadata\Type\Binary;
use POData\Providers\Metadata\Type\Boolean;
use POData\Providers\Metadata\Type\Byte;
use POData\Providers\Metadata\Type\DateTime;
use POData\Providers\Metadata\Type\DateTimeTz;
use POData\Providers\Metadata\Type\Decimal;
use POData\Providers\Metadata\Type\Double;
use POData\Providers\Metadata\Type\Guid;
use POData\Providers\Metadata\Type\Int16;
use POData\Providers\Metadata\Type\Int32;
use POData\Providers\Metadata\Type\Int64;
use POData\Providers\Metadata\Type\SByte;
use POData\Providers\Metadata\Type\Single;
use POData\Providers\Metadata\Type\StringType;
use POData\Providers\Metadata\Type\EdmPrimitiveType;
use POData\Providers\Metadata\Type\IType;
use POData\Providers\Metadata\Type\Time;
use POData\Common\Messages;
use POData\Common\InvalidOperationException;
use ReflectionClass;
use InvalidArgumentException;

/**
 * Class ResourceType
 *
 * A type to describe an entity type, complex type or primitive type.
 *
 * @package POData\Providers\Metadata
 */
class ResourceType
{
    /**
     * Name of the resource described by this class instance.
     *
     * @var string
     */
    protected $_name;

    /**
     * Namespace name in which resource described by this class instance
     * belongs to.
     *
     * @var string
     */
    protected $_namespaceName;

    /**
     * The fully qualified name of the resource described by this class instance.
     *
     * @var string
     */
    protected $_fullName;

    /**
     * The type the resource described by this class instance.
     * Note: either Entity or Complex Type
     *
     * @var ResourceTypeKind|int
     */
    protected $_resourceTypeKind;

    /**
     * @var boolean
     */
    protected $_abstractType;

    /**
     * Refrence to ResourceType instance for base type, if any.
     *
     * @var ResourceType
     */
    protected $_baseType;

    /**
     * Collection of ResourceProperty for all properties declared on the
     * resource described by this class instance (This does not include
     * base type properties).
     *
     * @var ResourceProperty[] indexed by name
     */
    protected $_propertiesDeclaredOnThisType = array();

    /**
     * Collection of ResourceStreamInfo for all named streams declared on
     * the resource described by this class instance (This does not include
     * base type properties).
     *
     * @var ResourceStreamInfo[] indexed by name
     */
    protected $_namedStreamsDeclaredOnThisType = array();

    /**
     * Collection of ReflectionProperty instances for each property declared
     * on this type
     *
     * @var array(ResourceProperty, ReflectionProperty)
     */
    protected $_propertyInfosDeclaredOnThisType = array();

    /**
     * Collection of ResourceProperty for all properties declared on this type.
     * and base types.
     *
     * @var ResourceProperty[] indexed by name
     */
    protected $_allProperties = array();

    /**
     * Collection of ResourceStreamInfo for all named streams declared on this type.
     * and base types
     *
     * @var ResourceStreamInfo[]
     */
    protected $_allNamedStreams = array();

    /**
     * Collection of properties which has etag defined subset of $_allProperties
     * @var ResourceProperty[]
     */
    protected $_etagProperties = array();

    /**
     * Collection of key properties subset of $_allProperties
     *
     * @var ResourceProperty[]
     */
    protected $_keyProperties = array();

    /**
     * Whether the resource type described by this class instance is a MLE or not
     *
     * @var boolean
     */
    protected $_isMediaLinkEntry = false;

    /**
     * Whether the resource type described by this class instance has bag properties
     * Note: This has been initialized with null, later in hasBagProperty method,
     * this flag will be set to boolean value
     *
     * @var boolean
     */
    protected $_hasBagProperty = null;

    /**
     * Whether the resource type described by this class instance has named streams
     * Note: This has been intitialized with null, later in hasNamedStreams method,
     * this flag will be set to boolean value
     *
     * @var boolean
     */
    protected $_hasNamedStreams = null;

    /**
     * ReflectionClass (for complex/Entity) or IType (for Primitive) instance for
     * the resource (type) described by this class instance
     *
     * @var ReflectionClass|IType
     */
    protected $_type;

    /**
     * To store any custom information related to this class instance
     *
     * @var Object
     */
    protected $_customState;

    /**
     * Array to detect looping in bag's complex type
     *
     * @var array
     */
    protected $_arrayToDetectLoopInComplexBag;

    /**
     * Create new instance of ResourceType
     *
     * @param ReflectionClass|IType $instanceType     Instance type for the resource,
     *                                                for entity and
     *                                                complex this will
     *                                                be 'ReflectionClass' and for
     *                                                primitive type this
     *                                                will be IType
     * @param ResourceTypeKind|int  $resourceTypeKind Kind of resource (Entity, Complex or Primitive)
     * @param string                $name             Name of the resource
     * @param string                $namespaceName    Namespace of the resource
     * @param ResourceType          $baseType         Base type of the resource, if exists
     *
     * @param boolean               $isAbstract       Whether resource is abstract
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        $instanceType,
        $resourceTypeKind,
        $name,
        $namespaceName = null,
        ResourceType $baseType = null,
        $isAbstract = false
    ) {
        $this->_type = $instanceType;
        if ($resourceTypeKind == ResourceTypeKind::PRIMITIVE) {
            if ($baseType != null) {
                throw new InvalidArgumentException(
                    Messages::resourceTypeNoBaseTypeForPrimitive()
                );
            }

            if ($isAbstract) {
                throw new InvalidArgumentException(
                    Messages::resourceTypeNoAbstractForPrimitive()
                );
            }

            if (!($instanceType instanceof IType)) {
                throw new InvalidArgumentException(
                    Messages::resourceTypeTypeShouldImplementIType('$instanceType')
                );
            }
        } else {
            if (!($instanceType instanceof ReflectionClass)) {
                throw new InvalidArgumentException(
                    Messages::resourceTypeTypeShouldReflectionClass('$instanceType')
                );
            }
        }

        $this->_resourceTypeKind = $resourceTypeKind;
        $this->_name = $name;
        $this->_baseType = $baseType;
        $this->_namespaceName = $namespaceName;
        $this->_fullName
            = is_null($namespaceName) ? $name : $namespaceName . '.' . $name;
        $this->_abstractType = $isAbstract;
        $this->_isMediaLinkEntry = false;
        $this->_customState = null;
        $this->_arrayToDetectLoopInComplexBag = array();
        //TODO: Set MLE if base type has MLE Set
    }

    /**
     * Get reference to ResourceType for base class
     *
     * @return ResourceType
     */
    public function getBaseType()
    {
        return $this->_baseType;
    }

    /**
     * To check whether this resource type has base type
     *
     * @return boolean True if base type is defined, false otherwise
     */
    public function hasBaseType()
    {
        return !is_null($this->_baseType);
    }

    /**
     * To get custom state object for this type
     *
     * @return object
     */
    public function getCustomState()
    {
        return $this->_customState;
    }

    /**
     * To set custom state object for this type
     *
     * @param ResourceSet $object The custom object.
     *
     * @return void
     */
    public function setCustomState($object)
    {
        $this->_customState = $object;
    }

    /**
     * Get the instance type. If the resource type describes a complex or entity type
     * then this function returns refernece to ReflectionClass instance for the type.
     * If resource type describes a primitive type then this function returns ITYpe.
     *
     * @return ReflectionClass|IType
     */
    public function getInstanceType()
    {
        return $this->_type;
    }

    /**
     * Get name of the type described by this resource type
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get the namespace under which the type described by this resource type is
     * defined.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->_namespaceName;
    }

    /**
     * Get full name (namespacename.typename) of the type described by this resource
     * type.
     *
     * @return string
     */
    public function getFullName()
    {
        return $this->_fullName;
    }

    /**
     * To check whether the type described by this resource type is abstract or not
     *
     * @return boolean True if type is abstract else False
     */
    public function isAbstract()
    {
        return $this->_abstractType;
    }

    /**
     * To get the kind of type described by this resource class
     *
     * @return ResourceTypeKind
     */
    public function getResourceTypeKind()
    {
        return $this->_resourceTypeKind;
    }

    /**
     * To check whether the type described by this resource type is MLE
     *
     * @return boolean True if type is MLE else False
     */
    public function isMediaLinkEntry()
    {
        return $this->_isMediaLinkEntry;
    }

    /**
     * Set the resource type as MLE or non-MLE
     *
     * @param boolean $isMLE True to set as MLE, false for non-MLE
     *
     * @return void
     */
    public function setMediaLinkEntry($isMLE)
    {
        if ($this->_resourceTypeKind != ResourceTypeKind::ENTITY) {
            throw new InvalidOperationException(
                Messages::resourceTypeHasStreamAttributeOnlyAppliesToEntityType()
            );
        }

        $this->_isMediaLinkEntry = $isMLE;
    }

    /**
     * Add a property belongs to this resource type instance
     *
     * @param ResourceProperty $property Property to add
     *
     * @throws InvalidOperationException
     * @return void
     */
    public function addProperty(ResourceProperty $property)
    {
        if ($this->_resourceTypeKind == ResourceTypeKind::PRIMITIVE) {
            throw new InvalidOperationException(
                Messages::resourceTypeNoAddPropertyForPrimitive()
            );
        }

        $name = $property->getName();
        foreach (array_keys($this->_propertiesDeclaredOnThisType) as $propertyName) {
            if (strcasecmp($propertyName, $name) == 0) {
                throw new InvalidOperationException(
                    Messages::resourceTypePropertyWithSameNameAlreadyExists(
                        $propertyName, $this->_name
                    )
                );
            }
        }

        if ($property->isKindOf(ResourcePropertyKind::KEY)) {
            if ($this->_resourceTypeKind != ResourceTypeKind::ENTITY) {
                throw new InvalidOperationException(
                    Messages::resourceTypeKeyPropertiesOnlyOnEntityTypes()
                );
            }

            if ($this->_baseType != null) {
                throw new InvalidOperationException(
                    Messages::resourceTypeNoKeysInDerivedTypes()
                );
            }
        }

        if ($property->isKindOf(ResourcePropertyKind::ETAG)
            && ($this->_resourceTypeKind != ResourceTypeKind::ENTITY)
        ) {
            throw new InvalidOperationException(
                Messages::resourceTypeETagPropertiesOnlyOnEntityTypes()
            );
        }

        //Check for Base class properties
        $this->_propertiesDeclaredOnThisType[$name] = $property;
        // Set $this->_allProperties to null, this is very important because the
        // first call to getAllProperties will initilaize $this->_allProperties,
        // further call to getAllProperties will not reinitialize _allProperties
        // so if addProperty is called after calling getAllProperties then the
        // property just added will not be reflected in $this->_allProperties
        unset($this->_allProperties);
        $this->_allProperties = array();
    }

    /**
     * Get collection properties belongs to this resource type (excluding base class
     * properties). This function returns  empty array in case of resource type
     * for primitive types.
     *
     * @return ResourceProperty[]
     */
    public function getPropertiesDeclaredOnThisType()
    {
        return $this->_propertiesDeclaredOnThisType;
    }

    /**
     * Get collection properties belongs to this resource type including base class
     * properties. This function returns  empty array in case of resource type
     * for primitive types.
     *
     * @return ResourceProperty[]
     */
    public function getAllProperties()
    {
        if (empty($this->_allProperties)) {
            if ($this->_baseType != null) {
                $this->_allProperties = $this->_baseType->getAllProperties();
            }

            $this->_allProperties = array_merge(
                $this->_allProperties, $this->_propertiesDeclaredOnThisType
            );
        }

        return $this->_allProperties;
    }

    /**
     * Get collection key properties belongs to this resource type. This
     * function returns non-empty array only for resource type representing
     * an entity type.
     *
     * @return ResourceProperty[]
     */
    public function getKeyProperties()
    {
        if (empty($this->_keyProperties)) {
            $baseType = $this;
            while ($baseType->_baseType != null) {
                $baseType = $baseType->_baseType;
            }

            foreach ($baseType->_propertiesDeclaredOnThisType
                as $propertyName => $resourceProperty
            ) {
                if ($resourceProperty->isKindOf(ResourcePropertyKind::KEY) || $resourceProperty->isKindOf(ResourcePropertyKind::KEY_RESOURCE_REFERENCE)) {
                    $this->_keyProperties[$propertyName] = $resourceProperty;
                }
            }
        }

        return $this->_keyProperties;
    }

    /**
     * Get collection of e-tag properties belongs to this type.
     *
     * @return ResourceProperty[]
     */
    public function getETagProperties()
    {
        if (empty ($this->_etagProperties)) {
            foreach ($this->getAllProperties()
                as $propertyName => $resourceProperty
            ) {
                if ($resourceProperty->isKindOf(ResourcePropertyKind::ETAG)) {
                    $this->_etagProperties[$propertyName] = $resourceProperty;
                }
            }
        }

        return $this->_etagProperties;
    }

    /**
     * To check this type has any eTag properties
     *
     * @return boolean
     */
    public function hasETagProperties()
    {
        $this->getETagProperties();
        return !empty($this->_etagProperties);
    }

    /**
     * Try to get ResourceProperty for a property defined for this resource type
     * excluding base class properties
     *
     * @param string $propertyName The name of the property to resolve.
     *
     * @return ResourceProperty|null
     */
    public function resolvePropertyDeclaredOnThisType($propertyName)
    {
        if (array_key_exists($propertyName, $this->_propertiesDeclaredOnThisType)) {
            return $this->_propertiesDeclaredOnThisType[$propertyName];
        }

        return null;
    }

    /**
     * Try to get ResourceProperty for a property defined for this resource type
     * including base class properties
     *
     * @param string $propertyName The name of the property to resolve.
     *
     * @return ResourceProperty|null
     */
    public function resolveProperty($propertyName)
    {
        if (array_key_exists($propertyName, $this->getAllProperties())) {
            return $this->_allProperties[$propertyName];
        }

        return null;
    }

    /**
     * Add a named stream belongs to this resource type instance
     *
     * @param ResourceStreamInfo $namedStream ResourceStreamInfo instance describing the named stream to add.
     *
     * @return void
     *
     * @throws InvalidOperationException
     */
    public function addNamedStream(ResourceStreamInfo $namedStream)
    {
        if ($this->_resourceTypeKind != ResourceTypeKind::ENTITY) {
            throw new InvalidOperationException(
                Messages::resourceTypeNamedStreamsOnlyApplyToEntityType()
            );
        }

        $name = $namedStream->getName();
        foreach (array_keys($this->_namedStreamsDeclaredOnThisType)
            as $namedStreamName
        ) {
            if (strcasecmp($namedStreamName, $name) == 0) {
                throw new InvalidOperationException(
                    Messages::resourceTypeNamedStreamWithSameNameAlreadyExists(
                        $name, $this->_name
                    )
                );
            }
        }

        $this->_namedStreamsDeclaredOnThisType[$name] = $namedStream;
        // Set $this->_allNamedStreams to null, the first call to getAllNamedStreams
        // will initialize $this->_allNamedStreams, further call to
        // getAllNamedStreams will not reinitialize _allNamedStreams
        // so if addNamedStream is called after calling getAllNamedStreams then the
        // property just added will not be reflected in $this->_allNamedStreams
        unset($this->_allNamedStreams);
        $this->_allNamedStreams = array();
    }

    /**
     * Get collection of ResourceStreamInfo describing the named streams belongs
     * to this resource type (excluding base class properties)
     *
     * @return ResourceStreamInfo[]
     */
    public function getNamedStreamsDeclaredOnThisType()
    {
        return $this->_namedStreamsDeclaredOnThisType;
    }

    /**
     * Get collection of ResourceStreamInfo describing the named streams belongs
     * to this resource type including base class named streams.
     *
     * @return ResourceStreamInfo[]
     */
    public function getAllNamedStreams()
    {
        if (empty($this->_allNamedStreams)) {
            if ($this->_baseType != null) {
                $this->_allNamedStreams = $this->_baseType->getAllNamedStreams();
            }

            $this->_allNamedStreams
                = array_merge(
                    $this->_allNamedStreams,
                    $this->_namedStreamsDeclaredOnThisType
                );
        }

        return $this->_allNamedStreams;
    }

    /**
     * Try to get ResourceStreamInfo for a named stream defined for this
     * resource type excluding base class named streams
     *
     * @param string $namedStreamName Name of the named stream to resolve.
     *
     * @return ResourceStreamInfo|null
     */
    public function tryResolveNamedStreamDeclaredOnThisTypeByName($namedStreamName)
    {
        if (array_key_exists($namedStreamName, $this->_namedStreamsDeclaredOnThisType)) {
            return $this->_namedStreamsDeclaredOnThisType[$namedStreamName];
        }

        return null;
    }

    /**
     * Try to get ResourceStreamInfo for a named stream defined for this resource
     * type including base class named streams
     *
     * @param string $namedStreamName Name of the named stream to resolve.
     *
     * @return ResourceStreamInfo|NULL
     */
    public function tryResolveNamedStreamByName($namedStreamName)
    {
        if (array_key_exists($namedStreamName, $this->getAllNamedStreams())) {
            return $this->_allNamedStreams[$namedStreamName];
        }

        return null;
    }

    /**
     * Check this resource type instance has named stream associated with it
     * Note: This is an internal method used by library. Devs don't use this.
     *
     * @return boolean true if resource type instance has named stream else false
     */
    public function hasNamedStream()
    {
        // Note: Calling this method will initialize _allNamedStreams
        // and _hasNamedStreams flag to a boolean value
        // from null depending on the current state of _allNamedStreams
        // array, so method should be called only after adding all
        // named streams
        if (is_null($this->_hasNamedStreams)) {
            $this->getAllNamedStreams();
            $this->_hasNamedStreams = !empty($this->_allNamedStreams);
        }

        return $this->_hasNamedStreams;
    }

    /**
     * Check this resource type instance has bag property associated with it
     * Note: This is an internal method used by library. Devs don't use this.
     *
     * @param array &$arrayToDetectLoopInComplexType array for detecting loop.
     *
     * @return boolean|null true if resource type instance has bag property else false
     */
    public function hasBagProperty(&$arrayToDetectLoopInComplexType)
    {
        // Note: Calling this method will initialize _bagProperties
        // and _hasBagProperty flag to a boolean value
        // from null depending on the current state of
        // _propertiesDeclaredOnThisType array, so method
        // should be called only after adding all properties
        if (!is_null($this->_hasBagProperty)) {
            return $this->_hasBagProperty;
        }

        if ($this->_baseType != null && $this->_baseType->hasBagProperty($arrayToDetectLoopInComplexType)) {
            $this->_hasBagProperty = true;
        } else {
            foreach ($this->_propertiesDeclaredOnThisType as $resourceProperty) {
                $hasBagInComplex = false;
                if ($resourceProperty->isKindOf(ResourcePropertyKind::COMPLEX_TYPE)) {
                    //We can say current ResouceType ("this")
                    //is contains a bag property if:
                    //1. It contain a property of kind bag.
                    //2. It contains a normal complex property
                    //with a sub property of kind bag.
                    //The second case can be further expanded, i.e.
                    //if the normal complex property
                    //has a normal complex sub property with a
                    //sub property of kind bag.
                    //So for complex type we recursively call this
                    //function to check for bag.
                    //Shown below how looping can happen in complex type:
                    //Customer ResourceType (id1)
                    //{
                    //  ....
                    //  Address: Address ResourceType (id2)
                    //  {
                    //    .....
                    //    AltAddress: Address ResourceType (id2)
                    //    {
                    //      ...
                    //    }
                    //  }
                    //}
                    //
                    //Here the resource type of Customer::Address and
                    //Customer::Address::AltAddress
                    //are same, this is a loop, we need to detect
                    //this and avoid infinite recursive loop.
                    //
                    $count = count($arrayToDetectLoopInComplexType);
                    $foundLoop = false;
                    for ($i = 0; $i < $count; $i++) {
                        if ($arrayToDetectLoopInComplexType[$i] === $resourceProperty->getResourceType()) {
                            $foundLoop = true;
                            break;
                        }
                    }

                    if (!$foundLoop) {
                        $arrayToDetectLoopInComplexType[$count] = $resourceProperty->getResourceType();
                        $hasBagInComplex = $resourceProperty->getResourceType()->hasBagProperty($arrayToDetectLoopInComplexType);
                        unset($arrayToDetectLoopInComplexType[$count]);
                    }
                }

                if ($resourceProperty->isKindOf(ResourcePropertyKind::BAG) || $hasBagInComplex) {
                    $this->_hasBagProperty = true;
                    break;
                }
            }
        }


        return $this->_hasBagProperty;
    }

    /**
     * Validate the type
     *
     * @return void
     *
     * @throws InvalidOperationException
     */
    public function validateType()
    {
        $keyProperties = $this->getKeyProperties();
        if (($this->_resourceTypeKind == ResourceTypeKind::ENTITY) && empty($keyProperties)) {
            throw new InvalidOperationException(
                Messages::resourceTypeMissingKeyPropertiesForEntity(
                    $this->getFullName()
                )
            );
        }
    }

    /**
     * To check the type described by this resource type is assignable from
     * a type described by another resource type. Or this type is a sub-type
     * of (derived from the) given resource type
     *
     * @param ResourceType $resourceType Another resource type.
     *
     * @return boolean
     */
    public function isAssignableFrom(ResourceType $resourceType)
    {
        $base = $resourceType;
        while ($base != null) {
            if ($this == $base) {
                return true;
            }

            $base = $base->_baseType;
        }

        return false;
    }

    /**
     * Get predefined ResourceType for a primitive type
     *
     * @param EdmPrimitiveType|int $typeCode Typecode of primitive type
     *
     * @return ResourceType
     *
     * @throws InvalidArgumentException
     */
    public static function getPrimitiveResourceType($typeCode)
    {
        switch ($typeCode) {
            case EdmPrimitiveType::BINARY:
                return new ResourceType(
                    new Binary(), ResourceTypeKind::PRIMITIVE,
                    'Binary', 'Edm'
                );
                break;
            case EdmPrimitiveType::BOOLEAN:
                return new ResourceType(
                    new Boolean(),
                    ResourceTypeKind::PRIMITIVE,
                    'Boolean', 'Edm'
                );
                break;
            case EdmPrimitiveType::BYTE:
                return new ResourceType(
                    new Byte(),
                    ResourceTypeKind::PRIMITIVE,
                    'Byte', 'Edm'
                );
                break;
            case EdmPrimitiveType::DATE:
                return new ResourceType(
                    new DateTime(),
                    ResourceTypeKind::PRIMITIVE,
                    'Date', 'Edm'
                );
                break;
            case EdmPrimitiveType::DATETIME:
                return new ResourceType(
                    new DateTime(),
                    ResourceTypeKind::PRIMITIVE,
                    'DateTime', 'Edm'
                );
                break;
            case EdmPrimitiveType::DATETIMETZ:
                return new ResourceType(
                    new DateTimeTz(),
                    ResourceTypeKind::PRIMITIVE,
                    'DateTime', 'Edm'
                );
                break;
            case EdmPrimitiveType::DECIMAL:
                return new ResourceType(
                    new Decimal(),
                    ResourceTypeKind::PRIMITIVE,
                    'Decimal', 'Edm'
                );
                break;
            case EdmPrimitiveType::DOUBLE:
                return new ResourceType(
                    new Double(),
                    ResourceTypeKind::PRIMITIVE,
                    'Double', 'Edm'
                );
                break;
            case EdmPrimitiveType::GUID:
                return new ResourceType(
                    new Guid(),
                    ResourceTypeKind::PRIMITIVE,
                    'Guid', 'Edm'
                );
                break;
            case EdmPrimitiveType::INT16:
                return new ResourceType(
                    new Int16(),
                    ResourceTypeKind::PRIMITIVE,
                    'Int16', 'Edm'
                );
                break;
            case EdmPrimitiveType::INT32:
                return new ResourceType(
                    new Int32(),
                    ResourceTypeKind::PRIMITIVE,
                    'Int32', 'Edm'
                );
                break;
            case EdmPrimitiveType::INT64:
                return new ResourceType(
                    new Int64(),
                    ResourceTypeKind::PRIMITIVE,
                    'Int64', 'Edm'
                );
                break;
            case EdmPrimitiveType::SBYTE:
                return new ResourceType(
                    new SByte(),
                    ResourceTypeKind::PRIMITIVE,
                    'SByte', 'Edm'
                );
                break;
            case EdmPrimitiveType::SINGLE:
                return new ResourceType(
                    new Single(),
                    ResourceTypeKind::PRIMITIVE,
                    'Single', 'Edm'
                );
                break;
            case EdmPrimitiveType::STRING:
                return new ResourceType(
                    new StringType(),
                    ResourceTypeKind::PRIMITIVE,
                    'String', 'Edm'
                );
                break;
            case EdmPrimitiveType::TIMESPAN:
                return new ResourceType(
                    new StringType(),
                    ResourceTypeKind::PRIMITIVE,
                    'TimeSpan', 'Edm'
                );
                break;
            default:
                throw new InvalidArgumentException(
                    Messages::commonNotValidPrimitiveEDMType(
                        '$typeCode', 'getPrimitiveResourceType'
                    )
                );
        }
    }


    function __wakeup() {
        if ($this->_type instanceof ReflectionClass) {
            $this->_type = new ReflectionClass($this->_type->name);
        }
    }

    public function __serialize()
    {
        return [
            '_name' => $this->_name,
            '_namespaceName' => $this->_namespaceName,
            '_fullName' => $this->_fullName,
            '_resourceTypeKind' => $this->_resourceTypeKind,
            '_abstractType' => $this->_abstractType,
            '_baseType' => $this->_baseType,
            '_propertiesDeclaredOnThisType' => $this->_propertiesDeclaredOnThisType,
            '_namedStreamsDeclaredOnThisType' => $this->_namedStreamsDeclaredOnThisType,
            '_propertyInfosDeclaredOnThisType' => $this->_propertyInfosDeclaredOnThisType,
            '_allProperties' => $this->_allProperties,
            '_allNamedStreams' => $this->_allNamedStreams,
            '_etagProperties' => $this->_etagProperties,
            '_keyProperties' => $this->_keyProperties,
            '_isMediaLinkEntry' => $this->_isMediaLinkEntry,
            '_hasBagProperty' => $this->_hasBagProperty,
            '_hasNamedStreams' => $this->_hasNamedStreams,
            '_type' => $this->_type instanceof ReflectionClass ? $this->_type->getName() : $this->_type,
            '_customState' => $this->_customState,
            '_arrayToDetectLoopInComplexBag' => $this->_arrayToDetectLoopInComplexBag
        ];
    }

    public function __unserialize($data) {
        $this->_name = $data["_name"];
        $this->_namespaceName = $data["_namespaceName"];
        $this->_fullName = $data["_fullName"];
        $this->_resourceTypeKind = $data["_resourceTypeKind"];
        $this->_abstractType = $data["_abstractType"];
        $this->_baseType = $data["_baseType"];
        $this->_propertiesDeclaredOnThisType = $data["_propertiesDeclaredOnThisType"];
        $this->_namedStreamsDeclaredOnThisType = $data["_namedStreamsDeclaredOnThisType"];
        $this->_propertyInfosDeclaredOnThisType = $data["_propertyInfosDeclaredOnThisType"];
        $this->_allProperties = $data["_allProperties"];
        $this->_allNamedStreams = $data["_allNamedStreams"];
        $this->_etagProperties = $data["_etagProperties"];
        $this->_keyProperties = $data["_keyProperties"];
        $this->_isMediaLinkEntry = $data["_isMediaLinkEntry"];
        $this->_hasBagProperty = $data["_hasBagProperty"];
        $this->_hasNamedStreams = $data["_hasNamedStreams"];
        $this->_type = is_string($data["_type"]) ? new ReflectionClass($data["_type"]) : $data["_type"];
        $this->_customState = $data["_customState"];
        $this->_arrayToDetectLoopInComplexBag = $data["_arrayToDetectLoopInComplexBag"];
    }
}
