<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'bookstore';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

function executeQuery($pdo, $query, $params) {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['action'])) {
    $from_date = $_GET['from_date'];
    $to_date = $_GET['to_date'];
    $genre = $_GET['genre'] ?? null;
    $limit = intval($_GET['limit'] ?? 5);

    if ($_GET['action'] === 'top-authors') {
        $query = "
            SELECT a.name, a.birth_date, COALESCE(SUM(s.quantity), 0) AS total_sales
            FROM authors a
            LEFT JOIN book_author ba ON a.id = ba.author_id
            LEFT JOIN books b ON ba.book_id = b.id
            LEFT JOIN sales s ON b.id = s.book_id AND s.sale_date BETWEEN :from_date AND :to_date
            LEFT JOIN book_genre bg ON b.id = bg.book_id
            LEFT JOIN genres g ON bg.genre_id = g.id
        ";

        if ($genre) {
            $query .= " AND g.name = :genre";
        }

        $query .= " GROUP BY a.id ORDER BY total_sales DESC LIMIT :limit";

        $params = [
            ':from_date' => $from_date,
            ':to_date' => $to_date,
            ':limit' => $limit
        ];
        if ($genre) {
            $params[':genre'] = $genre;
        }

        $result = executeQuery($pdo, $query, $params);
        echo json_encode($result);

    } elseif ($_GET['action'] === 'top-books') {
        $query = "SELECT
    b.title,
    b.publication_year,
    s.sale_date,
    s.quantity AS total_amount,
    GROUP_CONCAT(DISTINCT g.name) AS genres,
    GROUP_CONCAT(DISTINCT a.name) AS authors
FROM
    sales s
JOIN
    books b ON s.book_id = b.id
JOIN
    book_author ba ON b.id = ba.book_id
JOIN
    authors a ON ba.author_id = a.id
JOIN
    book_genre bg ON b.id = bg.book_id
JOIN
    genres g ON bg.genre_id = g.id
WHERE
    s.sale_date = (
        SELECT
            MAX(s2.sale_date)
        FROM
            sales s2
        WHERE
            s2.book_id = s.book_id
            AND s2.quantity = (
                SELECT
                    MAX(s3.quantity)
                FROM
                    sales s3
                WHERE
                    s3.book_id = s2.book_id
            )
            AND s2.sale_date BETWEEN :from_date AND :to_date
    )
    AND s.quantity = (
        SELECT
            MAX(s4.quantity)
        FROM
            sales s4
        WHERE
            s4.book_id = s.book_id
            AND s4.sale_date BETWEEN :from_date AND :to_date
    )
    AND s.sale_date BETWEEN :from_date AND :to_date
";

        if ($genre) {
            $query .= " AND g.name = :genre";
        }

        $query .= " GROUP BY
    b.id
ORDER BY
    total_amount DESC
LIMIT :limit;
";

        $params = [
            ':from_date' => $from_date,
            ':to_date' => $to_date,
            ':limit' => $limit
        ];
        if ($genre) {
            $params[':genre'] = $genre;
        }

        $result = executeQuery($pdo, $query, $params);
        echo json_encode($result);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>

