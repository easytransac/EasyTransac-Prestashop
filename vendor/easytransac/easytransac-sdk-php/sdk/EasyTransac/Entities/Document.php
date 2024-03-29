<?php

namespace EasyTransac\Entities;

/**
 * Represents a document
 * @copyright EasyTransac
 */
class Document extends Entity
{
    /** @map:Id **/
    protected $id = null;

    /** @map:DocumentType **/
    protected $documentType = null;

    /** @map:Status **/
    protected $status = null;

    /** @map:Date **/
    protected $date = null;

    /** @map:DateUpdated **/
    protected $dateUpdated = null;

    /** @map:Content **/
    protected $content = null;

    /** @map:Comment **/
    protected $comment = null;

    /** @map:Extension **/
    protected $extension = null;

    public function getId()
    {
        return $this->id;
    }

    public function getDocumentType()
    {
        return $this->documentType;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getDateUpdated()
    {
        return $this->dateUpdated;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function setDocumentType($documentType)
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function setExtension($extension)
    {
        $this->extension = $extension;
        return $this;
    }
}
