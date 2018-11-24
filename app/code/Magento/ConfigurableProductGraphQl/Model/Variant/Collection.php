<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ConfigurableProductGraphQl\Model\Variant;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection as ChildCollection;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as DataProvider;

/**
 * Collection for fetching configurable child product data.
 */
class Collection
{
    /**
     * @var CollectionFactory
     */
    private $childCollectionFactory;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var DataProvider
     */
    private $productDataProvider;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var Product[]
     */
    private $parentProduct = [];

    /**
     * @var array
     */
    private $childrenMap = [];

    /**
     * @var string[]
     */
    private $attributeCodes = [];

    /**
     * @param CollectionFactory $childCollectionFactory
     * @param ProductFactory $productFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataProvider $productDataProvider
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        CollectionFactory $childCollectionFactory,
        ProductFactory $productFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataProvider $productDataProvider,
        MetadataPool $metadataPool
    ) {
        $this->childCollectionFactory = $childCollectionFactory;
        $this->productFactory = $productFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productDataProvider = $productDataProvider;
        $this->metadataPool = $metadataPool;
    }

    /**
     * Add parent to collection filter
     *
     * @param Product $product
     * @return void
     */
    public function addParentId(Product $product) : void
    {
        if (!in_array($product, $this->parentProduct) && !empty($this->childrenMap)) {
            $this->childrenMap = [];
            $this->parentProduct[] = $product;
        } elseif (!in_array($product, $this->parentProduct)) {
            $this->parentProduct[] = $product;
        }
    }

    /**
     * Add attributes to collection filter
     *
     * @param array $attributeCodes
     * @return void
     */
    public function addEavAttributes(array $attributeCodes) : void
    {
        $this->attributeCodes = array_replace($this->attributeCodes, $attributeCodes);
    }

    /**
     * Retrieve child products from for passed in parent id.
     *
     * @param int $id
     * @return array
     */
    public function getChildProductsByParentId(int $id) : array
    {
        $childrenMap = $this->fetch();

        if (!isset($childrenMap[$id])) {
            return [];
        }

        return $childrenMap[$id];
    }

    /**
     * Fetch all children products from parent id's.
     *
     * @return array
     * @throws \Exception
     */
    private function fetch() : array
    {
        if (empty($this->parentProduct) || !empty($this->childrenMap)) {
            return $this->childrenMap;
        }

        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        foreach ($this->parentProduct as $product) {

            $attributeData = $this->getAttributesCode($product);
            /** @var ChildCollection $childCollection */
            $childCollection = $this->childCollectionFactory->create();
            $childCollection->addAttributeToSelect($attributeData);

            /** @var Product $product */
            $product->setData($linkField, $product->getId());
            $childCollection->setProductFilter($product);

            /** @var Product $childProduct */
            foreach ($childCollection->getItems() as $childProduct) {
                $formattedChild = ['model' => $childProduct, 'sku' => $childProduct->getSku()];
                $parentId = (int)$childProduct->getParentId();
                if (!isset($this->childrenMap[$parentId])) {
                    $this->childrenMap[$parentId] = [];
                }

                $this->childrenMap[$parentId][] = $formattedChild;
            }
        }

        return $this->childrenMap;
    }

    /**
     * Get attributes code
     *
     * @param \Magento\Catalog\Model\Product $currentProduct
     * @return array
     */
    private function getAttributesCode(Product $currentProduct): array
    {
        $attributeCode = [];
        $allowAttributes = $currentProduct->getTypeInstance()->getConfigurableAttributes($currentProduct);
        foreach ($allowAttributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            if (!\in_array($productAttribute->getAttributeCode(), $attributeCode)) {
                $attributeCode[] = $productAttribute->getAttributeCode();
            }
        }

        return $attributeCode;
    }
}
