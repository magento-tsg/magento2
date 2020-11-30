<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CatalogUrlRewrite\Observer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogUrlRewrite\Model\Product\AppendUrlRewritesToProducts;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\StoreWebsiteRelationInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Class ProductProcessUrlRewriteSavingObserver
 *
 * Generates urls for product url rewrites
 */
class ProductProcessUrlRewriteSavingObserver implements ObserverInterface
{
    /**
     * @var UrlPersistInterface
     */
    private $urlPersist;

    /**
     * @var StoreWebsiteRelationInterface|null
     */
    private $storeWebsiteRelation;

    /**
     * @var AppendUrlRewritesToProducts
     */
    private $appendRewrites;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param UrlPersistInterface $urlPersist
     * @param StoreWebsiteRelationInterface|null $storeWebsiteRelation
     * @param AppendUrlRewritesToProducts|null $appendRewrites
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        UrlPersistInterface $urlPersist,
        StoreWebsiteRelationInterface $storeWebsiteRelation,
        AppendUrlRewritesToProducts $appendRewrites,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->urlPersist = $urlPersist;
        $this->storeWebsiteRelation = $storeWebsiteRelation;
        $this->appendRewrites = $appendRewrites;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Generate urls for UrlRewrite and save it in storage
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();

        if ($this->isNeedUpdateRewrites($product)) {
            $this->deleteObsoleteRewrites($product);
            $oldWebsiteIds = $product->getOrigData('website_ids');
            $storesToAdd = [];
            if ($oldWebsiteIds !== null) {
                $storesToAdd = $this->getStoresListByWebsiteIds(
                    array_diff($product->getWebsiteIds(), $oldWebsiteIds)
                );
            }
            $this->appendRewrites->execute([$product], $storesToAdd);
        }
    }

    /**
     * Remove obsolete Url rewrites
     *
     * @param Product $product
     */
    private function deleteObsoleteRewrites(Product $product): void
    {
        $oldWebsiteIds = $product->getOrigData('website_ids') ?? [];
        $storesToRemove = $this->getStoresListByWebsiteIds(
            array_diff($oldWebsiteIds, $product->getWebsiteIds())
        );
        if ((int)$product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
            $storesToRemove[] = $product->getStoreId();
        }
        if (!empty($storesToRemove)) {
            $this->urlPersist->deleteByData(
                [
                    UrlRewrite::ENTITY_ID => $product->getId(),
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::STORE_ID => $storesToRemove,
                ]
            );
        }
    }

    /**
     * Is website assignment updated
     *
     * @param Product $product
     * @return bool
     */
    private function isWebsiteChanged(Product $product)
    {
        $oldWebsiteIds = $product->getOrigData('website_ids');

        return array_values($oldWebsiteIds) !== array_values($product->getWebsiteIds());
    }

    /**
     * Retrieve list of stores by website ids
     *
     * @param array $websiteIds
     * @return array
     */
    private function getStoresListByWebsiteIds(array $websiteIds): array
    {
        $storeIdsArray = [];
        if (!empty($websiteIds)) {
            foreach ($websiteIds as $websiteId) {
                $storeIdsArray[] = $this->storeWebsiteRelation->getStoreByWebsiteId($websiteId);
            }
        }

        return array_merge([], ...$storeIdsArray);
    }

    /**
     * Return product use category path in rewrite config value
     *
     * @return bool
     */
    private function isRewriteUseCategoryPath(): bool
    {
        return $this->scopeConfig->isSetFlag('catalog/seo/product_use_categories');
    }

    /**
     * Is product rewrites need to be updated
     *
     * @param Product $product
     * @return bool
     */
    private function isNeedUpdateRewrites(Product $product): bool
    {
        return ($product->dataHasChangedFor('url_key')
                && (int)$product->getVisibility() !== Visibility::VISIBILITY_NOT_VISIBLE)
            || ($product->getIsChangedCategories() && $this->isRewriteUseCategoryPath())
            || $this->isWebsiteChanged($product)
            || $product->dataHasChangedFor('visibility');
    }
}
