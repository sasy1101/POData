<?php

namespace POData\Providers\Query;

use POData\UriProcessor\QueryProcessor\OrderByParser\InternalOrderByInfo;
use POData\Providers\Metadata\ResourceProperty;
use POData\UriProcessor\ResourcePathProcessor\SegmentParser\KeyDescriptor;
use POData\Providers\Metadata\ResourceSet;
use POData\Providers\Expression\IExpressionProvider;
use POData\Providers\Query\QueryType;
use POData\UriProcessor\QueryProcessor\ExpressionParser\FilterInfo;
use POData\UriProcessor\QueryProcessor\SkipTokenParser\SkipTokenInfo;
use POData\UriProcessor\RequestDescription;


/**
 * Class IQueryProvider
 * @package POData\Providers\Query
 */
interface IQueryProvider
{

    /**
     * Indicates if the QueryProvider can handle ordered paging, this means respecting order, skip, and top parameters
     * If the query provider can not handle ordered paging, it must return the entire result set and POData will
     * perform the ordering and paging
     *
     * @return Boolean True if the query provider can handle ordered paging, false if POData should perform the paging
     */
    public function handlesOrderedPaging();

    /**
     * Gets the expression provider used by to compile OData expressions into expression used by this query provider.
     *
     * @return IExpressionProvider
     */
    public function getExpressionProvider(RequestDescription $request);


    /**
     * Gets collection of entities belongs to an entity set
     * IE: http://host/EntitySet
     *  http://host/EntitySet?$skip=10&$top=5&filter=Prop gt Value
     *
     * @param string $queryType indicates if this is a query for a count, entities, or entities with a count
     * @param ResourceSet $resourceSet The entity set containing the entities to fetch
     * @param FilterInfo|null $filterInfo represents the $filter parameter of the OData query. NULL if no $filter specified
     * @param InternalOrderByInfo|null $orderby sorted order if we want to get the data in some specific order
     * @param int|null $top number of records which need to be shown
     * @param int|null $skip number of records which need to be skipped
     * @param SkipTokenInfo|null $skipToken value indicating what records to skip
     * @param ExpandedProjectionNode[] $expansion represents the $expand parameter of the OData query
     *
     * @return QueryResult
     */
    public function getResourceSet(
        string $queryType,
        ResourceSet $resourceSet,
        RequestDescription $request,
        FilterInfo $filterInfo = null,
        InternalOrderByInfo $orderBy = null,
        int $top = null,
        int $skip = null,
        SkipTokenInfo $skipToken = null,
        array $expansion = []
    );


    /**
     * Gets an entity instance from an entity set identified by a key
     * IE: http://host/EntitySet(1L)
     * http://host/EntitySet(KeyA=2L,KeyB='someValue')
     *
     * @param ResourceSet $resourceSet The entity set containing the entity to fetch
     * @param KeyDescriptor $keyDescriptor The key identifying the entity to fetch
     *
     * @return object|null Returns entity instance if found else null
     */
    public function getResourceFromResourceSet(
        ResourceSet $resourceSet,
        RequestDescription $request,
        KeyDescriptor $keyDescriptor
    );


    /**
     * Get related resource set for a resource
     * IE: http://host/EntitySet(1L)/NavigationPropertyToCollection
     * http://host/EntitySet?$expand=NavigationPropertyToCollection
     *
     * @param string $queryType indicates if this is a query for a count, entities, or entities with a count
     * @param ResourceSet $sourceResourceSet The entity set containing the source entity
     * @param object $sourceEntityInstance The source entity instance.
     * @param ResourceSet      $targetResourceSet    The resource set of containing the target of the navigation property
     * @param ResourceProperty $targetProperty       The navigation property to retrieve
     * @param FilterInfo  $filter represents the $filter parameter of the OData query.  NULL if no $filter specified
     * @param mixed $orderBy sorted order if we want to get the data in some specific order
     * @param int $top number of records which  need to be skip
     * @param String $skip value indicating what records to skip
     *
     * @return QueryResult
     *
     */
    public function getRelatedResourceSet(
        $queryType,
        ResourceSet $sourceResourceSet,
        $sourceEntityInstance,
        ResourceSet $targetResourceSet,
        ResourceProperty $targetProperty,
        $filter = null,
        $orderBy = null,
        $top = null,
        $skip = null
    );

    /**
     * Gets a related entity instance from an entity set identified by a key
     * IE: http://host/EntitySet(1L)/NavigationPropertyToCollection(33)
     *
     * @param ResourceSet $sourceResourceSet The entity set containing the source entity
     * @param object $sourceEntityInstance The source entity instance.
     * @param ResourceSet $targetResourceSet The entity set containing the entity to fetch
     * @param ResourceProperty $targetProperty The metadata of the target property.
     * @param KeyDescriptor $keyDescriptor The key identifying the entity to fetch
     *
     * @return object|null Returns entity instance if found else null
     */
    public function getResourceFromRelatedResourceSet(
        ResourceSet $sourceResourceSet,
        $sourceEntityInstance,
        ResourceSet $targetResourceSet,
        ResourceProperty $targetProperty,
        KeyDescriptor $keyDescriptor
    );




    /**
     * Get related resource for a resource
     * IE: http://host/EntitySet(1L)/NavigationPropertyToSingleEntity
     * http://host/EntitySet?$expand=NavigationPropertyToSingleEntity
     *
     * @param ResourceSet $sourceResourceSet The entity set containing the source entity
     * @param object $sourceEntityInstance The source entity instance.
     * @param ResourceSet $targetResourceSet The entity set containing the entity pointed to by the navigation property
     * @param ResourceProperty $targetProperty The navigation property to fetch
     *
     * @return object|null The related resource if found else null
     */
    public function getRelatedResourceReference(
        ResourceSet $sourceResourceSet,
        $sourceEntityInstance,
        ResourceSet $targetResourceSet,
        ResourceProperty $targetProperty
    );

    public function getCustomProperties($entryObject);

	public function postResource(
        ResourceSet $resourceSet,
        RequestDescription $request,
        $data
    );

	public function putResource(
		ResourceSet $resourceSet,
        RequestDescription $request,
        KeyDescriptor $keyDescriptor,
        $data,
        $filter,
        $expand
    );

	public function deleteResource(
		ResourceSet $resourceSet,
        RequestDescription $request,
        $keyDescriptor
    );
}
