<?php






namespace JsonSchema\Uri\Retrievers;







abstract class AbstractRetriever implements UriRetrieverInterface
{





protected $contentType;






public function getContentType()
{
return $this->contentType;
}
}
