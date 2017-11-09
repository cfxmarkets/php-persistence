<?php
namespace CFX\Persistence\Rest;

abstract class AbstractDatasource extends \CFX\Persistence\AbstractDatasource implements DatasourceInterface {
    public function get($q=null) {
        $endpoint = "/".$this->getResourceType();
        $q = $this->parseDSL($q);
        if (!$q->requestingCollection()) $endpoint .= "/".$q->getId();

        $r = $this->sendRequest('GET', $endpoint);
        $obj = json_decode($r->getBody(), true);
        if (!$obj && $this->debug) {
            throw new \RuntimeException("Uh oh! The CFX Api Server seems to have screwed up. It didn't return valid json data. Here's what it returned:\n\n".$r->getBody());
        }
        $obj = $obj['data'];

        // Convert to "table of rows" format for inflate
        if (!$q->requestingCollection()) $obj = [$obj];
        $obj = $this->inflateData($obj, $q->requestingCollection());

        return $obj;
    }

    public function getDuplicate(\CFX\JsonApi\ResourceInterface $r)
    {
        throw new \CFX\Persistence\ResourceNotFoundException("Not searching for duplicate resource.");
    }

    protected function saveNew(\CFX\JsonApi\ResourceInterface $r) {
        return $this->_saveRest('POST', "/".$this->getResourceType(), $r);
    }

    protected function saveExisting(\CFX\JsonApi\ResourceInterface $r) {
        return $this->_saveRest('PATCH', "/".$this->getResourceType()."/{$r->getId()}", $r);
    }

    /**
     * Convenience method for handling saveNew and saveExisting method calls, which are virtually
     * the same in REST with the exception of method and endpoint
     */
    protected function _saveRest($method, $endpoint, \CFX\JsonApi\ResourceInterface $r) {
        $response = $this->sendRequest($method, $endpoint, [ 'json' => [ 'data' => $r ] ]);

        // Use returned data to update resource
        $data = json_decode($response->getBody(), true)['data'];
        if (!$data && $this->debug) {
            throw new \RuntimeException("Uh oh! The CFX Api Server seems to have screwed up. It didn't return valid json data. Here's what it returned:\n\n".$response->getBody());
        }
        $this->currentData = $data;
        $r->restoreFromData();

        return $this;
    }

    public function delete($r) {
        if ($r instanceof \CFX\JsonApi\ResourceInterface) $r = $r->getId();
        $this->sendRequest('DELETE', "/".$this->getResourceType()."/$r");
        return $this;
    }

    /**
     * This method exists to allow datasources to set default parameters for their requests.
     * For example, certain datasources may always use an OAuth token for authorization,
     * and that can be set here before the request is sent to the context for processing.
     */
    protected function sendRequest($method, $endpoint, array $params=[]) {
        return $this->context->sendRequest($method, $endpoint, $params);
    }

    public function convert(\CFX\JsonApi\ResourceInterface $src, $convertTo) {
        return $src;
    }
}

