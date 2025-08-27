UPDATE wp_postmeta AS pm
INNER JOIN wp_posts AS p ON p.ID = pm.post_id
SET pm.meta_value = p.ID
WHERE 
    p.post_type IN ('product', 'product_variation')
    AND pm.meta_key = '_sku'
    AND (pm.meta_value IS NULL OR pm.meta_value = '');


INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT p.ID, '_sku', p.ID
FROM wp_posts AS p
LEFT JOIN wp_postmeta AS pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
WHERE 
    p.post_type IN ('product', 'product_variation')
    AND pm.meta_id IS NULL;


SELECT p.ID, p.post_type, pm.meta_value AS current_sku
FROM wp_posts AS p
INNER JOIN wp_postmeta AS pm ON p.ID = pm.post_id
WHERE 
    p.post_type IN ('product', 'product_variation')
    AND pm.meta_key = '_sku'
    AND (pm.meta_value IS NULL OR pm.meta_value = '');

SELECT p.ID, p.post_type
FROM wp_posts AS p
LEFT JOIN wp_postmeta AS pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
WHERE 
    p.post_type IN ('product', 'product_variation')
    AND pm.meta_id IS NULL;

