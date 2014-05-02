<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;


/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeType;

/**
 * ElasticSearchHelper
 */
class ElasticSearchHelper implements ProtectedContextAwareInterface {

	/**
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 * @Flow\Inject
	 */
	protected $systemLogger;


	/**
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface
	 */
	protected $logger;

	/**
	 * @Flow\Inject
	 * @var FulltextHelper
	 */
	protected $fulltextHelper;

	/**
	 * Create a new ElasticSearch query underneath the given $node
	 *
	 * @param NodeInterface $node
	 * @return ElasticSearchQueryBuilder
	 */
	public function query(NodeInterface $node) {
		return new ElasticSearchQueryBuilder($node);
	}

	/**
	 * Retrieve the fulltext indexing helpers
	 *
	 * @return FulltextHelper
	 */
	public function getFulltext() {
		return $this->fulltextHelper;
	}

	/**
	 * Build all path prefixes. From an input such as:
	 *
	 *   foo/bar/baz
	 *
	 * it emits an array with:
	 *
	 *   foo
	 *   foo/bar
	 *   foo/bar/baz
	 *
	 * This method works both with absolute and relative paths.
	 *
	 * @param string $path
	 * @return array<string>
	 */
	public function buildAllPathPrefixes($path) {
		if (strlen($path) === 0) {
			return array();
		} elseif ($path === '/') {
			return array('/');
		}

		$currentPath = '';
		if ($path{0} === '/') {
			$currentPath = '/';
		}
		$path = ltrim($path, '/');

		$pathPrefixes = array();
		foreach (explode('/', $path) as $pathPart) {
			$currentPath .= $pathPart . '/';
			$pathPrefixes[] = rtrim($currentPath, '/');
		}

		return $pathPrefixes;
	}

	/**
	 * Returns an array of node type names including the passed $nodeType and all its supertypes, recursively
	 *
	 * @param NodeType $nodeType
	 * @return array<String>
	 */
	public function extractNodeTypeNamesAndSupertypes(NodeType $nodeType) {
		$nodeTypeNames = array();
		$this->extractNodeTypeNamesAndSupertypesInternal($nodeType, $nodeTypeNames);
		return array_values($nodeTypeNames);
	}

	/**
	 * Recursive function for fetching all node type names
	 *
	 * @param NodeType $nodeType
	 * @param array $nodeTypeNames
	 * @return void
	 */
	protected function extractNodeTypeNamesAndSupertypesInternal(NodeType $nodeType, array &$nodeTypeNames) {
		$nodeTypeNames[$nodeType->getName()] = $nodeType->getName();
		foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
			$this->extractNodeTypeNamesAndSupertypesInternal($superType, $nodeTypeNames);
		}
	}

	/**
	 * Convert an array of nodes to an array of node identifiers
	 *
	 * @param array<NodeInterface> $nodes
	 * @return array
	 */
	public function convertArrayOfNodesToArrayOfNodeIdentifiers($nodes) {
		if (!is_array($nodes) && !$nodes instanceof \Traversable) {
			return array();
		}
		$nodeIdentifiers = array();
		foreach ($nodes as $node) {
			$nodeIdentifiers[] = $node->getIdentifier();
		}

		return $nodeIdentifiers;
	}

	/**
	 * TEST
	 * Convert an array of nodes to an array of node identifiers
	 *
	 * @param array<NodeInterface> $nodes
	 * @return array
	 */
	public function convertArrayOfNodesToFacetGroup($nodes) {

		if (!is_array($nodes) && !$nodes instanceof \Traversable) {
			return array();
		}
		$nodeFacets = array();

		foreach ($nodes as $node) {

			$nodeFacets[] = array(
				$node->getIdentifier() => array(
					"identifier" => $node->getIdentifier(),
					"nodeName" => $node->getProperty('title')
				)
			);

			/*
			$nodeFacets = array(
				"9a765467-3aa8-d515-15ce-9b90cd28a6b3" => array(
					"identifier" => "9a765467-3aa8-d515-15ce-9b90cd28a6b3",
					"nodeName" => "Bohren"

				),
				"3a44ac61-8372-01ba-ff12-39a875cc798f" => array(
					"identifier" => "3a44ac61-8372-01ba-ff12-39a875cc798f",
					"nodeName" => "Handrad"

				)
			);
			*/
			//$this->systemLogger->log(print_r($nodeFacets));
		}

		return $nodeFacets;
	}

	/**
	 * TEST
	 * Convert an array of nodes to an array of node identifiers
	 *
	 * @param NodeInterface $node
	 * @param string $facetType
	 * @return array
	 */
	public function convertParentNodeToFacets($node, $facetType = NULL) {
		$facetGroup = array();
		$nodeFacets = array();

		$childNodes = $node->getChildNodes($facetType);
		if (!is_array($childNodes) && !$childNodes instanceof \Traversable) {
			$this->systemLogger->log('kein Array', LOG_INFO);
			return array();
		}

		foreach ($childNodes as $childNode) {

			/*
			$nodeFacets[] = array(
				$childNode->getIdentifier() => array(
					"identifier" => $childNode->getIdentifier(),
					"nodeName" => $childNode->getName(),
					"nodeType" => $childNode->getNodeType()->getName()
				)
			);
			*/
			$nodeFacets[] = array(
				"identifier" => $childNode->getIdentifier(),
				"nodeName" => $childNode->getProperty('title'),
				"nodeType" => $childNode->getNodeType()->getName()
			);
		}

		$facetGroup['nodeType'] = $node->getNodeType()->getName();
		$facetGroup['name'] = $node->getProperty('title');
		$facetGroup['identifier'] = $node->getIdentifier();
		$facetGroup['isVisible'] = $node->isVisible();
		$facetGroup['facets'] = $nodeFacets;

		$this->systemLogger->log(print_r($facetGroup));

		return $facetGroup;
	}

	/**
	 * All methods are considered safe
	 *
	 * @param string $methodName
	 * @return boolean
	 */
	public function allowsCallOfMethod($methodName) {
		return TRUE;
	}
}