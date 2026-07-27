<?php
/**
 * Adjust quantity_sold for the stock row matching business_id + product_name.
 * $delta > 0 = a sale (increases quantity_sold / decreases remaining)
 * $delta < 0 = a restore (decreases quantity_sold / increases remaining)
 * Never lets quantity_sold go below 0. Does nothing if no matching stock row exists.
 */
function adjustStockSold(PDO $db, int $businessId, string $productName, int $delta): void {
    if ($delta === 0 || $businessId <= 0 || trim($productName) === '') {
        return;
    }

    $stmt = $db->prepare(
        "SELECT id, quantity_sold FROM stock
         WHERE business_id = ? AND LOWER(product_name) = LOWER(?)
         LIMIT 1"
    );
    $stmt->execute([$businessId, $productName]);
    $stockRow = $stmt->fetch();

    if (!$stockRow) {
        return;
    }

    $newSold = (int)$stockRow['quantity_sold'] + $delta;
    if ($newSold < 0) {
        $newSold = 0;
    }

    $db->prepare("UPDATE stock SET quantity_sold = ? WHERE id = ?")
       ->execute([$newSold, $stockRow['id']]);
}