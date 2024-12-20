<?php

/**
 * This file is a part of horstoeko/zugferdublbridge.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace horstoeko\zugferdublbridge;

use DOMNode;
use DOMXPath;
use DOMDocument;
use DOMNodeList;
use horstoeko\zugferdublbridge\traits\HandlesCallbacks;
use horstoeko\zugferdublbridge\xml\XmlNodeList;
use horstoeko\zugferdublbridge\XmlDocumentBase;
use RuntimeException;
use Throwable;

/**
 * Class representing the XML reader helper
 *
 * @category Zugferd-UBL-Bridge
 * @package  Zugferd-UBL-Bridge
 * @author   D. Erling <horstoeko@erling.com.de>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/horstoeko/zugferdublbridge
 */
class XmlDocumentReader extends XmlDocumentBase
{
    use HandlesCallbacks;

    /**
     * Internal XPath
     *
     * @var DOMXPath
     */
    protected $internalDomXPath = null;

    /**
     * Constructor
     *
     * @return XmlDocumentReader
     */
    public function __construct()
    {
        $this->internalDomDocument = new DOMDocument();
        $this->internalDomDocument->formatOutput = true;
    }

    /**
     * Add a namespace declaration to the root
     *
     * @param  string $namespace
     * @param  string $value
     * @return static
     */
    public function addNamespace(string $namespace, string $value)
    {
        return parent::addNamespace($namespace, $value);
    }

    /**
     * Load from XML string
     *
     * @param  string $source
     * @return XmlDocumentReader
     */
    public function loadFromXmlString(string $source): XmlDocumentReader
    {
        $prevUseInternalErrors = \libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();
            $this->internalDomDocument->loadXML($source);
            if (libxml_get_last_error()) {
                throw new RuntimeException("Invalid XML detected.");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Invalid XML detected.");
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prevUseInternalErrors);
        }

        $this->registerDomXPath();
        $this->registerNamespacesInDomXPath();

        return $this;
    }

    /**
     * Load from XML file
     *
     * @param  string $filename
     * @return XmlDocumentReader
     */
    public function loadFromXmlFile(string $filename): XmlDocumentReader
    {
        $prevUseInternalErrors = \libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();
            $this->internalDomDocument->load($filename);
            if (libxml_get_last_error()) {
                throw new RuntimeException("Invalid XML detected.");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Invalid XML detected.");
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prevUseInternalErrors);
        }

        $this->registerDomXPath();
        $this->registerNamespacesInDomXPath();

        return $this;
    }

    /**
     * Register the DOM XPath
     *
     * @return XmlDocumentReader
     */
    private function registerDomXPath(): XmlDocumentReader
    {
        $this->internalDomXPath = new DOMXPath($this->internalDomDocument);

        return $this;
    }

    /**
     * Register namespaches
     *
     * @return XmlDocumentReader
     */
    private function registerNamespacesInDomXPath(): XmlDocumentReader
    {
        foreach ($this->registeredNamespaces as $prefix => $namespace) {
            $this->internalDomXPath->registerNamespace($prefix, $namespace);
        }

        return $this;
    }

    /**
     * Returns true if the expression found anything
     *
     * @param  string       $expression
     * @param  DOMNode|null $contextNode
     * @return boolean
     */
    public function exists(string $expression, ?DOMNode $contextNode = null): bool
    {
        $nodeList = $this->query($expression, $contextNode);

        if ($nodeList === false) {
            return false;
        }

        if ($nodeList->count() == 0) {
            return false;
        }

        if (is_null($nodeList->item(0)->nodeValue) || $nodeList->item(0)->nodeValue == "") {
            return false;
        }

        return true;
    }

    /**
     * Executes the given XPath expression.
     *
     * @param  string       $expression
     * @param  DOMNode|null $contextNode
     * @return DOMNodeList|false
     */
    public function query(string $expression, ?DOMNode $contextNode = null)
    {
        return $this->internalDomXPath->query($expression, $contextNode, false);
    }

    /**
     * Returns the value of a query
     *
     * @param  string       $expression
     * @param  DOMNode|null $contextNode
     * @return string|null
     */
    public function queryValue(string $expression, ?DOMNode $contextNode = null): ?string
    {
        if (!$this->exists($expression, $contextNode)) {
            return null;
        }

        return $this->query($expression, $contextNode)->item(0)->nodeValue;
    }

    /**
     * Returns the value of a query
     *
     * @param  string       $expression
     * @param  DOMNode|null $contextNode
     * @return XmlNodeList;
     */
    public function queryAll(string $expression, ?DOMNode $contextNode = null): XmlNodeList
    {
        if (!$this->exists($expression, $contextNode)) {
            return XmlNodeList::createFromDomNodelist();
        }

        return XmlNodeList::createFromDomNodelist($this->query($expression, $contextNode));
    }

    /**
     * When an element can be queried the $callback is called otherwise $callbackElse
     *
     * @param  string        $expression
     * @param  DOMNode|null  $contextNode
     * @param  callable      $callback
     * @param  callable|null $callbackElse
     * @return XmlDocumentReader
     */
    public function whenExists(string $expression, ?DOMNode $contextNode, $callback, $callbackElse = null): XmlDocumentReader
    {
        if ($this->exists($expression, $contextNode)) {
            $this->fireCallback(
                $callback,
                $this->query($expression, $contextNode)->item(0),
                $this->query($expression, $contextNode)->item(0)->parentNode
            );
        } else {
            $this->fireCallback($callbackElse);
        }

        return $this;
    }

    /**
     * When an element cannot be queried the $callback is called otherwise $callbackElse
     *
     * @param  string        $expression
     * @param  DOMNode|null  $contextNode
     * @param  callable      $callback
     * @param  callable|null $callbackElse
     * @return XmlDocumentReader
     */
    public function whenNotExists(string $expression, ?DOMNode $contextNode, $callback, $callbackElse = null): XmlDocumentReader
    {
        if (!$this->exists($expression, $contextNode)) {
            $this->fireCallback($callback);
        } else {
            $this->fireCallback(
                $callbackElse,
                $this->query($expression, $contextNode)->item(0),
                $this->query($expression, $contextNode)->item(0)->parentNode);
        }

        return $this;
    }

    /**
     * When an element equals value(s) the $callback is called
     *
     * @param  string          $expression
     * @param  DOMNode|null    $contextNode
     * @param  string|string[] $values
     * @param  callable        $callback
     * @param  callable|null   $callbackElse
     * @return XmlDocumentReader
     */
    public function whenEquals(string $expression, ?DOMNode $contextNode, $values, $callback, $callbackElse = null): XmlDocumentReader
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $equals = false;

        foreach ($values as $value) {
            if ($this->queryValue($expression, $contextNode) === $value) {
                $equals = true;
                break;
            }
        }

        if ($equals === true) {
            $this->fireCallback(
                $callback,
                $this->query($expression, $contextNode)->item(0),
                $this->query($expression, $contextNode)->item(0)->parentNode
            );
        } else {
            $this->fireCallback($callbackElse);
        }

        return $this;
    }

    /**
     * When an element not equals value(s) the $callback is called
     *
     * @param  string          $expression
     * @param  DOMNode|null    $contextNode
     * @param  string|string[] $values
     * @param  callable        $callback
     * @param  callable|null   $callbackElse
     * @return XmlDocumentReader
     */
    public function whenNotEquals(string $expression, ?DOMNode $contextNode, $values, $callback, $callbackElse = null): XmlDocumentReader
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $equals = false;

        foreach ($values as $value) {
            if ($this->queryValue($expression, $contextNode) === $value) {
                $equals = true;
                break;
            }
        }

        if ($equals === false) {
            $this->fireCallback($callback);
        } else {
            $this->fireCallback(
                $callbackElse,
                $this->query($expression, $contextNode)->item(0),
                $this->query($expression, $contextNode)->item(0)->parentNode
            );
        }

        return $this;
    }

    /**
     * When one exists
     *
     * @param  array     $expressions
     * @param  DOMNode[] $contextNodes
     * @param  callable  $callback
     * @param  callable  $callbackElse
     * @return XmlDocumentReader
     */
    public function whenOneExists(array $expressions, array $contextNodes, $callback, $callbackElse = null): XmlDocumentReader
    {
        foreach ($expressions as $expressionIndex => $expression) {
            if ($this->exists($expression, $contextNodes[$expressionIndex])) {
                $this->fireCallback($callback, $this->query($expression, $contextNodes[$expressionIndex])->item(0), $expressionIndex, $expression);
                return $this;
            }
        }

        $this->fireCallback($callbackElse);

        return $this;
    }
}
