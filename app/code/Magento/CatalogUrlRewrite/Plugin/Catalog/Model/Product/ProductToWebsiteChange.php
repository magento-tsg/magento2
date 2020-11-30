<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogUrlRewrite\Plugin\Catalog\Model\Product;

use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogUrlRewrite\Model\Product\AppendUrlRewritesToProducts;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\StoreWebsiteRelationInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Update URL rewrites after website change
 */
class ProductToWebsiteChange
{
    /**
     * @var ProductUrlRewriteGenerator
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface
     */
    protected $urlPersist;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var StoreWebsiteRelationInterface
     */
    private $storeWebsiteRelation;

    /**
     * @var SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var Collection
     */
    private $productCollection;

    /**
     * @var AppendUrlRewritesToProducts
     */
    private $appendRewrites;

    /**
     * @param ProductUrlRewriteGenerator $productUrlRewriteGenerator
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param UrlPersistInterface $urlPersist
     * @param RequestInterface $request
     * @param StoreWebsiteRelationInterface $storeWebsiteRelation
     * @param Collection $productCollection
     * @param AppendUrlRewritesToProducts $appendRewrites
     */
    public function __construct(
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        SearchCriteriaBuilder $criteriaBuilder,
        UrlPersistInterface $urlPersist,
        RequestInterface $request,
        StoreWebsiteRelationInterface $storeWebsiteRelation,
        Collection $productCollection,
        AppendUrlRewritesToProducts $appendRewrites
    ) {
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
        $this->request = $request;
        $this->storeWebsiteRelation = $storeWebsiteRelation;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->productCollection = $productCollection;
        $this->appendRewrites = $appendRewrites;
    }

    /**
     * Update url rewrites after website changes
     *
     * @param ProductAction $subject
     * @param mixed $result
     * @param array $productIds
     * @param array $websiteIds
     * @param string $type
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterUpdateWebsites(
        ProductAction $subject,
        $result,
        array $productIds,
        array $websiteIds,
        string $type
    ): void {
        if (empty($websiteIds)) {
            return;
        }
        $storeIdsArray = [];
        foreach ($websiteIds as $websiteId) {
            $storeIdsArray[] = $this->storeWebsiteRelation->getStoreByWebsiteId($websiteId);
        }
        $storeIds = array_merge([], ...$storeIdsArray);
        // Remove the URLs from websites this product no longer belongs to
        if ($type == 'remove') {
            $this->urlPersist->deleteByData(
                [
                    UrlRewrite::ENTITY_ID => $productIds,
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::STORE_ID => $storeIds,
                ]
            );
        } else {
            $collection = $this->productCollection->addFieldToFilter('entity_id', ['in' => implode(',', $productIds)]);
            $this->appendRewrites->execute($collection->getItems(), $storeIds);
        }
    }
}
