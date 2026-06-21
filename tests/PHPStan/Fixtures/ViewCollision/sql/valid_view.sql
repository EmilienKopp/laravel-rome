SELECT
    CONCAT(u.first_name, ' ', u.last_name) AS name,
    u.email,
    u.status
FROM users u
