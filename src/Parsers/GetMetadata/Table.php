<?php

namespace PHRETS\Parsers\GetMetadata;

use Illuminate\Support\Collection;
use PHRETS\Http\Response;
use PHRETS\Parsers\XML;
use PHRETS\Session;

class Table extends Base
{
    public function parse(Session $rets, Response $response, $keyed_by): Collection
    {
        /** @var XML $parser */
        $parser = $rets->getConfiguration()->getStrategy()->provide(\PHRETS\Strategies\Strategy::PARSER_XML);
        $xml = $parser->parse($response);

        $collection = new Collection();

        if ($xml->METADATA) {
            foreach ($xml->METADATA->{'METADATA-TABLE'}->Field as $key => $value) {
                $metadata = new \PHRETS\Models\Metadata\Table();
                $metadata->setSession($rets);
                $this->loadFromXml($metadata, $value, $xml->METADATA->{'METADATA-TABLE'});
                $method = 'get' . $keyed_by;
                $collection->put((string) $metadata->$method(), $metadata);
            }
        }

        return $collection;
    }
}
