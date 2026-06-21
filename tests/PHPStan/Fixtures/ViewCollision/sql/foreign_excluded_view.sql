SELECT
    users.name,
    orders.email
FROM users
JOIN orders ON users.id = orders.user_id
