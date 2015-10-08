<?php

/*
 * This file is part of the MassiveSearchBundle
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Massive\Bundle\SearchBundle\Search;

use Massive\Bundle\SearchBundle\Search\Metadata\FieldEvaluator;
use Massive\Bundle\SearchBundle\Search\Metadata\IndexMetadata;

/**
 * Convert mapped objects to search documents.
 */
class ObjectToDocumentConverter
{
    /**
     * @var FieldEvaluator
     */
    private $fieldEvaluator;

    /**
     * @var Factory
     */
    private $factory;

    /**
     * @param Factory $factory
     * @param FieldEvaluator $fieldEvaluator
     */
    public function __construct(Factory $factory, FieldEvaluator $fieldEvaluator)
    {
        $this->factory = $factory;
        $this->fieldEvaluator = $fieldEvaluator;
    }

    /**
     * Return the field evaluator.
     *
     * @return FieldEvaluator
     */
    public function getFieldEvaluator()
    {
        return $this->fieldEvaluator;
    }

    /**
     * Map the given object to a new document using the
     * given metadata.
     *
     * @param IndexMetadata $metadata
     * @param object $object
     *
     * @return Document
     */
    public function objectToDocument(IndexMetadata $metadata, $object)
    {
        $indexNameField = $metadata->getIndexName();
        $idField = $metadata->getIdField();
        $urlField = $metadata->getUrlField();
        $titleField = $metadata->getTitleField();
        $descriptionField = $metadata->getDescriptionField();
        $imageUrlField = $metadata->getImageUrlField();
        $localeField = $metadata->getLocaleField();
        $fieldMapping = $metadata->getFieldMapping();

        $document = $this->factory->createDocument();
        $document->setIndex($this->fieldEvaluator->getValue($object, $indexNameField));
        $document->setId($this->fieldEvaluator->getValue($object, $idField));
        $document->setClass($metadata->getName());

        if ($urlField) {
            $url = $this->fieldEvaluator->getValue($object, $urlField);
            if ($url) {
                $document->setUrl($url);
            }
        }

        if ($titleField) {
            $title = $this->fieldEvaluator->getValue($object, $titleField);
            if ($title) {
                $document->setTitle($title);
            }
        }

        if ($descriptionField) {
            $description = $this->fieldEvaluator->getValue($object, $descriptionField);
            if ($description) {
                $document->setDescription($description);
            }
        }

        if ($imageUrlField) {
            $imageUrl = $this->fieldEvaluator->getValue($object, $imageUrlField);
            $document->setImageUrl($imageUrl);
        }

        if ($localeField) {
            $locale = $this->fieldEvaluator->getValue($object, $localeField);
            $document->setLocale($locale);
        }

        $this->populateDocument($document, $object, $fieldMapping);

        return $document;
    }

    /**
     * Populate the Document with the actual values from the object which
     * is being indexed.
     *
     * @param Document $document
     * @param mixed $object
     * @param array $fieldMapping
     * @param string $prefix Prefix the document field name (used when called recursively)
     *
     * @throws \InvalidArgumentException
     */
    private function populateDocument($document, $object, $fieldMapping, $prefix = '')
    {
        foreach ($fieldMapping as $fieldName => $mapping) {
            $requiredMappings = ['field', 'type'];

            foreach ($requiredMappings as $requiredMapping) {
                if (!isset($mapping[$requiredMapping])) {
                    throw new \RuntimeException(sprintf(
                        'Mapping for "%s" does not have "%s" key',
                        get_class($document), $requiredMapping
                    ));
                }
            }

            $mapping = array_merge([
                'stored' => true,
                'aggregate' => false,
                'indexed' => true,
            ], $mapping);

            if ($mapping['type'] == 'complex') {
                if (!isset($mapping['mapping'])) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            '"complex" field mappings must have an additional array key "mapping" ' .
                            'which contains the mapping for the complex structure in mapping: %s',
                            print_r($mapping, true)
                        )
                    );
                }

                $childObjects = $this->fieldEvaluator->getValue($object, $mapping['field']);

                if (null === $childObjects) {
                    continue;
                }

                foreach ($childObjects as $i => $childObject) {
                    $this->populateDocument(
                        $document,
                        $childObject,
                        $mapping['mapping']->getFieldMapping(),
                        $prefix . $fieldName . $i
                    );
                }

                continue;
            }

            $value = $this->fieldEvaluator->getValue($object, $mapping['field']);

            if ($value !== null && false === is_scalar($value) && false === is_array($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'Search field "%s" resolved to non-scalar value with type "%s". Only scalar (single) values can be indexed.',
                    $fieldName, gettype($value)
                ));
            }

            if ($mapping['type'] !== 'complex') {
                $document->addField(
                    $this->factory->createField(
                        $prefix . $fieldName,
                        $value,
                        $mapping['type'],
                        $mapping['stored'],
                        $mapping['indexed'],
                        $mapping['aggregate']
                    )
                );

                continue;
            }

            foreach ($value as $key => $itemValue) {
                $document->addField(
                    $this->factory->createField(
                        $prefix . $fieldName . $key,
                        $itemValue,
                        $mapping['type'],
                        $mapping['stored'],
                        $mapping['indexed'],
                        $mapping['aggregate']
                    )
                );
            }
        }
    }
}
