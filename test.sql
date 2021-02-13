SELECT users.id, users.username, users.fname, users.lname, users.department
FROM users AS users WHERE 1 ORDER BY 1 LIMIT 0, 10

SELECT assets.id, assets.type, assets.vendor, assets.model, assets.owner
FROM assets AS assets
WHERE assets.owner = '1' ORDER BY 1 LIMIT 0,
