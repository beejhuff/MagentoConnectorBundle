<?php

namespace Pim\Bundle\MagentoConnectorBundle\Normalizer;

/**
 * A normalizer to transform a product entity into an array
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductCreateNormalizer extends AbstractProductNormalizer
{
    /**
     * {@inheritDoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        return $this->getNormalizedProduct(
            $object,
            $context['magentoStoreViews'],
            $context['magentoAttributes'],
            $context['attributeSetId'],
            $context['defaultLocale'],
            $context['channel'],
            true
        );
    }
}