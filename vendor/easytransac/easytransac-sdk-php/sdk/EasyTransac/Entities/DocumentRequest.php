<?php

namespace EasyTransac\Entities;

/**
 * Represents arguments for request "adddocument"
 * @copyright EasyTransac
 */
class DocumentRequest extends Entity
{
    /** @object:Customer **/
    protected $customer = null;

    /** @object:User **/
    protected $User = null;

    /** @object:Document **/
    protected $document = null;

    /** @map:DocumentId **/
    protected $documentId = null;

    /** @map:ShowContent **/
    protected $showContent = null;

    public function setCustomer($customer)
    {
        $this->customer = $customer;
        return $this;
    }

    public function setUser($User)
    {
        $this->User = $User;
        return $this;
    }

    public function setDocument($document)
    {
        $this->document = $document;
        return $this;
    }

    public function setDocumentId($documentId)
    {
        $this->documentId = $documentId;
        return $this;
    }

    public function setShowContent($showContent)
    {
        $this->showContent = $showContent;
        return $this;
    }
}
