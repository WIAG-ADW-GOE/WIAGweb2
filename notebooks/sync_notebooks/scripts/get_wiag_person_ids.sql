SELECT DISTINCT 
  i.id,
  CASE 
    WHEN t_id.epc IS NOT NULL THEN t_id.epc
    WHEN t_id.epc IS NULL AND t_id.can IS NOT NULL THEN t_id.can
    WHEN t_id.epc IS NULL AND t_id.can IS NULL AND t_id.dreg_can IS NOT NULL THEN t_id.dreg_can
  END AS id_public,
  uext.value AS gsn
FROM 
  item AS i
JOIN 
  url_external AS uext ON uext.item_id = i.id AND uext.authority_id = 200
JOIN 
  item_name_role AS inr ON inr.item_id_name = i.id
JOIN 
  (SELECT DISTINCT 
      ic.item_id AS item_id,
      ic_ii.id_public AS epc,
      ic_iii.id_public AS can,
      ic_iv.id_public AS dreg_can
   FROM 
      item_corpus AS ic
   LEFT JOIN 
      item_corpus AS ic_ii ON ic_ii.item_id = ic.item_id AND ic_ii.corpus_id = 'epc'
   LEFT JOIN 
      item_corpus AS ic_iii ON ic_iii.item_id = ic.item_id AND ic_iii.corpus_id = 'can'
   LEFT JOIN 
      item_corpus AS ic_iv ON ic_iv.item_id = ic.item_id AND ic_iv.corpus_id = 'dreg-can'
   WHERE 
      ic.corpus_id IN ('epc', 'can', 'dreg-can')
  ) AS t_id ON t_id.item_id = i.id
WHERE 
  i.is_online = 1;