<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogUrlRewrite\Plugin\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Action;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreWebsiteRelationInterface;
use Magento\Store\Model\Website;
use Magento\Store\Model\WebsiteRepository;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea crontab
 * @magentoDbIsolation disabled
 */
class ProductToWebsiteChangeTest extends TestCase
{
    /**
     * @var Action
     */
    private $action;

    /**
     * @var StoreWebsiteRelationInterface
     */
    private $storeWebsiteRelation;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = Bootstrap::getObjectManager();
        $this->action = $objectManager->get(Action::class);
        $this->storeWebsiteRelation = $objectManager->get(StoreWebsiteRelationInterface::class);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple_with_url_key.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_store_group_and_store.php
     */
    public function testUpdateUrlRewrites()
    {
        /** @var Website $website */
        $website = $this->loadWebsiteByCode('test');
        $product = $this->getProductModel('simple1');
        $this->action->updateWebsites([$product->getId()], [$website->getId()], 'add');
        $storeIds = $this->storeWebsiteRelation->getStoreByWebsiteId($website->getId());

        $this->assertStringContainsString(
            $product->getUrlKey() . '.html',
            $product->setStoreId(reset($storeIds))->getProductUrl()
        );

        $this->action->updateWebsites([$product->getId()], [$website->getId()], 'remove');
        $product->setRequestPath('');
        $url = $product->setStoreId(reset($storeIds))->getProductUrl();
        $this->assertStringNotContainsString(
            $product->getUrlKey() . '.html',
            $url
        );
    }

    /**
     * @param $websiteCode
     * @return Website
     */
    private function loadWebsiteByCode($websiteCode)
    {
        $websiteRepository = Bootstrap::getObjectManager()->get(WebsiteRepository::class);
        try {
            $website = $websiteRepository->get($websiteCode);
        } catch (NoSuchEntityException $e) {
            $website = null;
            $this->fail("Couldn`t load website: {$websiteCode}");
        }

        return $website;
    }

    /**
     * @param string $sku
     * @param int|null $storeId
     * @return ProductInterface
     */
    private function getProductModel(string $sku, int $storeId = null): ProductInterface
    {
        try {
            $productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
            $product = $productRepository->get($sku, false, $storeId, true);
        } catch (NoSuchEntityException $e) {
            $product = null;
            $this->fail("Couldn`t load product: {$sku}");
        }

        return $product;
    }
}
