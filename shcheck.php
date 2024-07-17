<?php

function logError($message) {
    file_put_contents('error_log.txt', $message . PHP_EOL, FILE_APPEND);
}

function getShopifyProducts($shopUrl) {
    $url = rtrim($shopUrl, '/') . '/products.json';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Connection: keep-alive',
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $http_code != 200) {
        $error_message = 'Unable to fetch data from the URL. HTTP status code: ' . $http_code;
        $curl_error = curl_error($ch);
        if ($curl_error) {
            $error_message .= '. cURL error: ' . $curl_error;
        }
        curl_close($ch);
        logError($error_message);
        return ['error' => $error_message];
    }

    curl_close($ch);

    file_put_contents('debug_raw_response.json', $response);  // Log raw response for debugging

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $json_error_message = 'Error decoding JSON response: ' . json_last_error_msg();
        logError($json_error_message);
        return ['error' => $json_error_message];
    }
    if (!isset($data['products'])) {
        $no_products_error = 'No products found in the response.';
        logError($no_products_error);
        return ['error' => $no_products_error];
    }

    $products = [];
    foreach ($data['products'] as $product) {
        foreach ($product['variants'] as $variant) {
            $products[] = [
                'title' => $product['title'],
                'price' => floatval($variant['price']),
                'variant_id' => $variant['id']
            ];
        }
    }

    return $products;
}

function findMinMaxProducts($products) {
    $minProduct = null;
    $maxProduct = null;

    foreach ($products as $product) {
        if ($minProduct === null || $product['price'] < $minProduct['price']) {
            $minProduct = $product;
        }
        if ($maxProduct === null || $product['price'] > $maxProduct['price']) {
            $maxProduct = $product;
        }
    }

    return [$minProduct, $maxProduct];
}

function formatResponse($minProduct, $maxProduct) {
    return json_encode([
        'Minimum Priced Product' => [
            'Title' => $minProduct['title'],
            'Price' => '$' . number_format($minProduct['price'], 2),
            'Variant ID' => $minProduct['variant_id']
        ],
        'Maximum Priced Product' => [
            'Title' => $maxProduct['title'],
            'Price' => '$' . number_format($maxProduct['price'], 2),
            'Variant ID' => $maxProduct['variant_id']
        ]
    ], JSON_PRETTY_PRINT);
}

try {
    if (isset($_GET['shopUrl'])) {
        $shopUrl = $_GET['shopUrl'];
        $products = getShopifyProducts($shopUrl);

        if (is_array($products) && isset($products['error'])) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => $products['error']], JSON_PRETTY_PRINT);
        } else if ($products !== false) {
            list($minProduct, $maxProduct) = findMinMaxProducts($products);

            header('Content-Type: application/json');
            echo formatResponse($minProduct, $maxProduct);
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Unable to retrieve products from the specified shop URL.'], JSON_PRETTY_PRINT);
        }
    } else {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Missing shopUrl parameter.'], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    logError('Exception: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'An unexpected error occurred.'], JSON_PRETTY_PRINT);
}
?>
