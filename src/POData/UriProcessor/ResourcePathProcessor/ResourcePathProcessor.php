<?php

namespace POData\UriProcessor\ResourcePathProcessor;

use POData\Providers\Query\QueryType;
use POData\UriProcessor\ResourcePathProcessor\SegmentParser\SegmentParser;
use POData\UriProcessor\ResourcePathProcessor\SegmentParser\TargetKind;
use POData\UriProcessor\RequestDescription;
use POData\IService;
use POData\Common\Url;
use POData\Common\ODataConstants;
use POData\Common\Messages;
use POData\Common\ODataException;
use POData\OperationContext\HTTPRequestMethod;
use POData\UriProcessor\UriProcessor;


/**
 * Class ResourcePathProcessor
 * @package POData\UriProcessor\ResourcePathProcessor
 */
class ResourcePathProcessor
{
    /**
     * Process the request Uri and creates an instance of
     * RequestDescription from the processed uri.
     *
     * @param IService $service        Reference to the data service instance.
     *
     * @return RequestDescription
     *
     * @throws ODataException If any exception occurs while processing the segments
     *                        or in case of any version incompatibility.
     */
    public static function process(IService $service)
	{
        $host = $service->getHost();
        $fullAbsoluteRequestUri = $host->getFullAbsoluteRequestUri();
        $absoluteRequestUri = $host->getAbsoluteRequestUri();
        $absoluteServiceUri = $host->getAbsoluteServiceUri();

        $requestUriSegments = array_slice(
            $absoluteRequestUri->getSegments(),
            $absoluteServiceUri->getSegmentCount()
        );
        $segments = SegmentParser::parseRequestUriSegments(
            $requestUriSegments,
            $service->getProvidersWrapper(),
            true
        );


        $dataType = null;
        $operationContext = $service->getOperationContext();
        if ($operationContext && $operationContext->incomingRequest()->getMethod() != HTTPRequestMethod::GET) {
            $dataType = $service->getHost()->getRequestContentType();
        }
        $request = new RequestDescription(
            $segments,
            $fullAbsoluteRequestUri,
            $service->getConfiguration()->getMaxDataServiceVersion(),
            $host->getRequestVersion(),
            $host->getRequestMaxVersion(),
            $dataType,
            $service
        );
        $kind = $request->getTargetKind();

        if ($kind == TargetKind::METADATA || $kind == TargetKind::SERVICE_DIRECTORY) {
            return $request;
        }


        $lambda = function(RequestDescription &$request) use (&$service) {
            $kind = $request->getTargetKind();
            $segments = $request->getSegments();

            if ($kind == TargetKind::PRIMITIVE_VALUE || $kind == TargetKind::MEDIA_RESOURCE) {
                // http://odata/NW.svc/Orders/$count
                // http://odata/NW.svc/Orders(123)/Customer/CustomerID/$value
                // http://odata/NW.svc/Employees(1)/$value
                // http://odata/NW.svc/Employees(1)/ThumbNail_48X48/$value
                $request->setContainerName($segments[count($segments) - 2]->getIdentifier());
            } else {
                $request->setContainerName($request->getIdentifier());
            }

            if ($request->getIdentifier() === ODataConstants::URI_COUNT_SEGMENT) {
                if (!$service->getConfiguration()->getAcceptCountRequests()) {
                    throw ODataException::createBadRequestError(Messages::configurationCountNotAccepted());
                }

                $request->queryType = QueryType::COUNT;
                // use of $count requires request DataServiceVersion
                // and MaxDataServiceVersion greater than or equal to 2.0

                $request->raiseResponseVersion(2, 0);
                $request->raiseMinVersionRequirement(2, 0);

            } else if ($request->isNamedStream()) {
                $request->raiseMinVersionRequirement(3, 0);
            } else if ($request->getTargetKind() == TargetKind::RESOURCE) {
                if (!$request->isLinkUri()) {
                    $resourceSetWrapper = $request->getTargetResourceSetWrapper();
                    //assert($resourceSetWrapper != null)
                    $hasNamedStream = $resourceSetWrapper->hasNamedStreams($service->getProvidersWrapper());

                    $hasBagProperty = $resourceSetWrapper->hasBagProperty($service->getProvidersWrapper());

                    if ($hasNamedStream || $hasBagProperty) {
                        $request->raiseResponseVersion(3, 0);
                    }
                }
            } else if ($request->getTargetKind() == TargetKind::BAG
            ) {
                $request->raiseResponseVersion(3, 0);
            }
        };

        // $uriProcessor = UriProcessor::process($this);
        foreach ($request->getParts() as &$part) {

            $lambda($part);
            
            $uriProcessor = UriProcessor::processPart($service, $part);

        }

        $lambda($request);
        return $request;
    }
}
