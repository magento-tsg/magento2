<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogUrlRewrite\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\ProductUrlRewriteDataLocator;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Service\V1\StoreViewService;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;

/**
 * Update url rewrites to products class
 */
class AppendUrlRewritesToProducts
{
    /**
     * @var ProductUrlRewriteGenerator
     */
    private $productUrlRewriteGenerator;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreViewService
     */
    private $storeViewService;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductUrlPathGenerator
     */
    private $productUrlPathGenerator;

    /**
     * @var UrlPersistInterface
     */
    private $urlPersist;

    /**
     * @var ProductUrlRewriteDataLocator
     */
    private $dataLocator;

    /**
     * AppendUrlRewritesToProducts constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param ProductUrlRewriteGenerator $urlRewriteGenerator
     * @param StoreViewService $storeViewService
     * @param StoreManagerInterface $storeManager
     * @param ProductUrlPathGenerator $urlPathGenerator
     * @param UrlPersistInterface $urlPersist
     * @param ProductUrlRewriteDataLocator $dataLocator
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductUrlRewriteGenerator $urlRewriteGenerator,
        StoreViewService $storeViewService,
        StoreManagerInterface $storeManager,
        ProductUrlPathGenerator $urlPathGenerator,
        UrlPersistInterface $urlPersist,
        ProductUrlRewriteDataLocator $dataLocator
    ) {
        $this->productRepository = $productRepository;
        $this->productUrlRewriteGenerator = $urlRewriteGenerator;
        $this->storeViewService = $storeViewService;
        $this->storeManager = $storeManager;
        $this->productUrlPathGenerator = $urlPathGenerator;
        $this->urlPersist = $urlPersist;
        $this->dataLocator = $dataLocator;
    }

    /**
     * Update existing rewrites and add for specific stores websites
     *
     * @param ProductInterface[] $products
     * @param array $storesToAdd
     */
    public function execute(array $products, array $storesToAdd = []): void
    {
        foreach ($products as $product) {
            $forceGenerateDefault = false;
            foreach ($storesToAdd as $storeId) {
                $hasScopeUrlKey = $this->storeViewService->doesEntityHaveOverriddenUrlKeyForStore(
                    $storeId,
                    $product->getId(),
                    Product::ENTITY
                );
                if ($hasScopeUrlKey) {
                    $urls[] = $this->generateUrls($product, (int)$storeId);
                } else {
                    $forceGenerateDefault = true;
                }
            }
            if ($forceGenerateDefault && $product->getStoreId() !== Store::DEFAULT_STORE_ID) {
                $urls[] = $this->generateUrls($product, Store::DEFAULT_STORE_ID);
            } elseif ($product->getStoreId() === Store::DEFAULT_STORE_ID
                || in_array($product->getStoreId(), $product->getStoreIds())) {
                $product->unsUrlPath();
                $product->setUrlPath($this->productUrlPathGenerator->getUrlPath($product));
                $urls[] = $this->productUrlRewriteGenerator->generate($product);
            }
        }
        if (!empty($urls)) {
            $this->urlPersist->replace(array_merge(...$urls));
        }
    }

    /**
     * Generate urls for sprcific store
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return array
     */
    private function generateUrls(ProductInterface $product, int $storeId): array
    {
        $storeData = $this->dataLocator->getRewriteGenerateData($product, $storeId);
        $urlKey = $product->getUrlKey();
        $visibility = $product->getVisibility();
        $origStoreId = $product->getStoreId();
        $product->setStoreId($storeId);
        $product->setVisibility($storeData['visibility'] ?? Product\Visibility::VISIBILITY_NOT_VISIBLE);
        $product->setUrlKey($storeData['url_key'] ?? '');
        $product->unsUrlPath();
        $product->setUrlPath($this->productUrlPathGenerator->getUrlPath($product));
        $urls = $this->productUrlRewriteGenerator->generate($product);
        $product->setVisibility($visibility);
        $product->setUrlKey($urlKey);
        $product->setStoreId($origStoreId);

        return $urls;
    }
}
