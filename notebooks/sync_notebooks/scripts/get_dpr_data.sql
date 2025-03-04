SELECT persons.wiag, persons.id, gsn.id, gsn.nummer 
FROM items 
INNER JOIN persons ON persons.item_id = items.id AND persons.deleted=0 AND items.deleted=0 AND items.status = "online" 
INNER JOIN gsn ON gsn.item_id = items.id AND gsn.deleted=0 
WHERE persons.wiag IS NOT NULL AND persons.wiag != '' 
group by persons.wiag 
having gsn.id=min(gsn.id)