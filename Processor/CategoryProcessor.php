<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\CategoryNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\NormalizeException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\CategoryNotMappedException;

/**
 * Magento category processor.
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryProcessor extends AbstractProcessor
{
    /** @var string */
    protected $categoryMapping;

    /** @var MagentoMappingMerger */
    protected $categoryMappingMerger;

    /** @var CategoryNormalizer */
    protected $categoryNormalizer;

    /** @var boolean */
    protected $isAnchor;

    /** @var boolean */
    protected $urlKey;

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param NormalizerGuesser                   $normalizerGuesser
     * @param LocaleManager                       $localeManager
     * @param MagentoMappingMerger                $storeViewMappingMerger
     * @param MagentoMappingMerger                $categoryMappingMerger
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        MagentoMappingMerger $categoryMappingMerger,
        MagentoSoapClientParametersRegistry $clientParametersRegistry
    ) {
        parent::__construct(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $clientParametersRegistry
        );

        $this->categoryMappingMerger = $categoryMappingMerger;
    }

    /**
     * Get category mapping from merger.
     *
     * @return string JSON
     */
    public function getCategoryMapping()
    {
        return json_encode($this->categoryMappingMerger->getMapping()->toArray());
    }

    /**
     * Set category mapping in parameters AND in database.
     *
     * @param string $categoryMapping JSON
     *
     * @return CategoryProcessor
     */
    public function setCategoryMapping($categoryMapping)
    {
        $decodedCategoryMapping = json_decode($categoryMapping, true);

        if (!is_array($decodedCategoryMapping)) {
            $decodedCategoryMapping = [$decodedCategoryMapping];
        }

        $this->categoryMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
        $this->categoryMappingMerger->setMapping($decodedCategoryMapping);
        $this->categoryMapping = $this->getCategoryMapping();

        return $this;
    }

    /**
     * @return boolean
     */
    public function isIsAnchor()
    {
        return $this->isAnchor;
    }

    /**
     * @param boolean $isAnchor
     *
     * @return CategoryProcessor
     */
    public function setIsAnchor($isAnchor)
    {
        $this->isAnchor = $isAnchor;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isUrlKey()
    {
        return $this->urlKey;
    }

    /**
     * @param boolean $urlKey
     *
     * @return CategoryProcessor
     */
    public function setUrlKey($urlKey)
    {
        $this->urlKey = $urlKey;

        return $this;
    }

    /**
     * Function called before all process.
     */
    protected function beforeExecute()
    {
        parent::beforeExecute();

        $this->categoryNormalizer = $this->normalizerGuesser->getCategoryNormalizer($this->getClientParameters());

        $magentoStoreViews = $this->webservice->getStoreViewsList();
        $magentoCategories = $this->webservice->getCategoriesStatus();

        $this->globalContext = array_merge(
            $this->globalContext,
            [
                'magentoCategories' => $magentoCategories,
                'magentoUrl'        => $this->getSoapUrl(),
                'defaultLocale'     => $this->defaultLocale,
                'magentoStoreViews' => $magentoStoreViews,
                'categoryMapping'   => $this->categoryMappingMerger->getMapping(),
                'defaultStoreView'  => $this->getDefaultStoreView(),
                'is_anchor'         => $this->isAnchor,
                'urlKey'            => $this->urlKey,
            ]
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidItemException
     */
    public function process($categories)
    {
        $this->beforeExecute();

        $normalizedCategories = [
            'create'    => [],
            'update'    => [],
            'move'      => [],
            'variation' => [],
        ];

        $categories = is_array($categories) ? $categories : [$categories];

        foreach ($categories as $category) {
            if ($category->getParent()) {
                try {
                    $normalizedCategory = $this->categoryNormalizer->normalize(
                        $category,
                        AbstractNormalizer::MAGENTO_FORMAT,
                        $this->globalContext
                    );

                    $normalizedCategories = array_merge_recursive($normalizedCategories, $normalizedCategory);
                } catch (CategoryNotMappedException $e) {
                    if (null !== $category->getParent() && $category->getParent()->isRoot()) {
                        throw new InvalidItemException(
                            $e->getMessage(),
                            [
                                'category_id'      => $category->getId(),
                                'category_code'    => $category->getCode(),
                                'category_label'   => $category->getLabel(),
                                'root_category_id' => $category->getRoot(),
                            ]
                        );
                    }
                } catch (NormalizeException $e) {
                    throw new InvalidItemException(
                        $e->getMessage(),
                        [
                            'category_id'      => $category->getId(),
                            'category_code'    => $category->getCode(),
                            'category_label'   => $category->getLabel(),
                            'root_category_id' => $category->getRoot(),
                        ]
                    );
                }
            }
        }

        return $normalizedCategories;
    }

    /**
     * Called after the configuration is set.
     */
    protected function afterConfigurationSet()
    {
        parent::afterConfigurationSet();

        $this->categoryMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            [
                'isAnchor' => [
                    'type'    => 'checkbox',
                    'options' => [
                        'help'  => 'pim_magento_connector.export.isAnchor.help',
                        'label' => 'pim_magento_connector.export.isAnchor.label',
                    ],
                ],
                'urlKey' => [
                    'type'    => 'checkbox',
                    'options' => [
                        'help'  => 'pim_magento_connector.export.urlKey.help',
                        'label' => 'pim_magento_connector.export.urlKey.label',
                    ],
                ],
            ],
            $this->categoryMappingMerger->getConfigurationField()
        );
    }
}
