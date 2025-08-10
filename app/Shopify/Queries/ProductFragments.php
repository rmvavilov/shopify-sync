<?php

namespace App\Shopify\Queries;

final class ProductFragments
{
    public static function fields(): string
    {
        return <<<'GQL'
fragment ProductFields on Product {
  id
  title
  descriptionHtml
  handle
  status
  productType
  totalInventory
  updatedAt
  priceRangeV2 {
    minVariantPrice { amount currencyCode }
    maxVariantPrice { amount currencyCode }
  }
  featuredMedia {
    ... on MediaImage {
      image   { url(transform:{maxWidth:360}) altText width height }
      preview { image { url(transform:{maxWidth:800}) altText width height } }
    }
    ... on Video        { preview { image { url(transform:{maxWidth:800}) altText width height } } }
    ... on ExternalVideo{ preview { image { url(transform:{maxWidth:800}) altText width height } } }
    ... on Model3d      { preview { image { url(transform:{maxWidth:800}) altText width height } } }
  }
  media(first: 2) {
    nodes {
      ... on MediaImage {
        image   { url(transform:{maxWidth:800}) altText width height }
        preview { image { url(transform:{maxWidth:800}) altText width height } }
      }
      ... on Video        { preview { image { url(transform:{maxWidth:800}) altText width height } } }
      ... on ExternalVideo{ preview { image { url(transform:{maxWidth:800}) altText width height } } }
      ... on Model3d      { preview { image { url(transform:{maxWidth:800}) altText width height } } }
    }
  }
  variants(first: 1) {
    edges {
      node {
        id
        price
        compareAtPrice
        image { url(transform:{maxWidth:800}) altText width height }
      }
    }
  }
  onlineStoreUrl
  onlineStorePreviewUrl
}
GQL;
    }
}
