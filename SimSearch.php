<?php
/**
 * کلاس جستجوی سیم‌کارت – نسخه نهایی با نمایش رکوردهای بدون قیمت در انتها
 * 
 * رفتار:
 * - رکوردهای با sf_price > 0 همیشه در ابتدای نتایج قرار می‌گیرند.
 * - رکوردهای با sf_price = 0 یا NULL همیشه در انتهای نتایج قرار می‌گیرند.
 * - مرتب‌سازی بر اساس فیلد انتخابی کاربر (مثلاً قیمت، شماره، ...) روی رکوردهای دارای قیمت اعمال می‌شود.
 */
class SimSearch
{
    private PDO $conn;
    private array $operatorLookup;
    private array $statusMap;
    private bool $debug = false;

    public function __construct(PDO $connection, array $operatorLookup, array $statusMap, bool $debug = false)
    {
        $this->conn = $connection;
        $this->operatorLookup = $operatorLookup;
        $this->statusMap = $statusMap;
        $this->debug = $debug;
    }

    public function execute(array $filters, int $perPage, int $currentPage): array
    {
        try {
            $query = $this->buildBaseQuery();
            $this->applyFilters($query, $filters);

            if ($this->debug) {
                error_log("SQL before count: " . $query['sql']);
                error_log("Params: " . json_encode($query['params']));
            }

            $total = $this->getTotalCount($query);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $currentPage = max(1, min($currentPage, $totalPages));
            $offset = ($currentPage - 1) * $perPage;

            $sort = $this->validateSort($filters['sort'] ?? 'sf_price');
            $order = $this->validateOrder($filters['order'] ?? 'asc');
            
            // ===== مرتب‌سازی اجباری: رکوردهای بدون قیمت در انتها =====
            // این شرط بدون توجه به انتخاب کاربر اعمال می‌شود
            $query['sql'] .= sprintf(
                " ORDER BY (CASE WHEN sf_price IS NULL OR sf_price <= 0 THEN 1 ELSE 0 END) ASC, `%s` %s",
                $sort,
                $order
            );

            $query['sql'] .= " LIMIT :limit OFFSET :offset";
            if ($this->debug) {
                error_log("Final SQL: " . $query['sql']);
                error_log("Params: " . json_encode($query['params']));
            }

            $stmt = $this->conn->prepare($query['sql']);
            foreach ($query['params'] as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'results'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total'       => $total,
                'totalPages'  => $totalPages,
                'currentPage' => $currentPage
            ];
        } catch (PDOException $e) {
            error_log("Search Error: " . $e->getMessage());
            return ['results' => [], 'total' => 0, 'totalPages' => 1, 'currentPage' => 1];
        }
    }

    public function getAllUniquePreNumbers(): array
    {
        $stmt = $this->conn->query("SELECT DISTINCT pre_number FROM sim_cards ORDER BY pre_number");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ======================== متدهای خصوصی ========================

    private function buildBaseQuery(): array
    {
        return [
            'sql'        => 'SELECT sim_number, pre_number, sim_type, status, sf_price, special_sale, readable_numbers, last_update, featured FROM sim_cards',
            'params'     => [],
            'conditions' => []
        ];
    }

    private function applyFilters(array &$query, array $filters): void
    {
        $this->applyPreNumberFilter($query, $filters['pre_number'] ?? null);
        $this->applyOperatorFilter($query, $filters['operator'] ?? null);
        $this->applyStatusFilter($query, $filters['status'] ?? null);
        $this->applyDigitFilter($query, $filters['digits'] ?? []);
        $this->applyPriceFilter($query, $filters['price_range'] ?? []);
        $this->applyFlagFilters($query, $filters);

        if (!empty($query['conditions'])) {
            $query['sql'] .= ' WHERE ' . implode(' AND ', $query['conditions']);
        }
    }

    private function applyPreNumberFilter(array &$query, $preNumbers): void
    {
        $preNumbers = (array) $preNumbers;
        $valid = array_filter($preNumbers, fn($p) => preg_match('/^9\d{2,3}$/', $p));
        if (empty($valid)) return;

        $ors = [];
        foreach ($valid as $pre) {
            $len = strlen($pre);
            $key = ":pre_" . md5($pre);
            $query['params'][$key] = $pre;
            $ors[] = "SUBSTRING(sim_number, 2, $len) = $key";
        }
        $query['conditions'][] = '(' . implode(' OR ', $ors) . ')';
    }

    private function applyOperatorFilter(array &$query, $operators): void
    {
        $operators = (array) $operators;
        $ors = [];
        foreach ($operators as $op) {
            $prefixes = array_keys($this->operatorLookup, $op);
            foreach ($prefixes as $pre) {
                $len = strlen($pre);
                $key = ":op_" . md5($pre);
                $query['params'][$key] = $pre;
                $ors[] = "SUBSTRING(sim_number, 2, $len) = $key";
            }
        }
        if (!empty($ors)) {
            $query['conditions'][] = '(' . implode(' OR ', $ors) . ')';
        }
    }

    private function applyStatusFilter(array &$query, $statuses): void
    {
        $statuses = (array) $statuses;
        $validStatusIds = [];
        foreach ($statuses as $statusName) {
            $normalized = preg_replace('/\s+/', ' ', trim($statusName));
            $statusId = array_search($normalized, $this->statusMap);
            if ($statusId !== false) {
                $validStatusIds[] = (int) $statusId;
            }
        }
        $validStatusIds = array_unique($validStatusIds);
        if (empty($validStatusIds)) return;

        $placeholders = [];
        foreach ($validStatusIds as $i => $id) {
            $key = ":status_$i";
            $placeholders[] = $key;
            $query['params'][$key] = $id;
        }
        $query['conditions'][] = "status IN (" . implode(',', $placeholders) . ")";
    }

    private function applyDigitFilter(array &$query, array $digits): void
    {
        $digits = array_filter($digits, fn($d) => $d !== '' && $d !== null);
        if (empty($digits)) return;

        $pattern = '';
        for ($pos = 1; $pos <= 11; $pos++) {
            if (isset($digits[$pos]) && $digits[$pos] !== '') {
                $pattern .= $digits[$pos];
            } else {
                $pattern .= '_';
            }
        }
        $key = ':digit_pattern';
        $query['params'][$key] = $pattern;
        $query['conditions'][] = "sim_number LIKE $key";
    }

    private function applyPriceFilter(array &$query, array $priceRange): void
    {
        $min = (float) ($priceRange['min'] ?? 0);
        $max = (float) ($priceRange['max'] ?? 0);
        if ($min <= 0 && $max <= 0) return;

        $conditions = [];
        if ($min > 0) {
            $key = ':min_price';
            $query['params'][$key] = $min;
            $conditions[] = "sf_price >= $key";
        }
        if ($max > 0) {
            $key = ':max_price';
            $query['params'][$key] = $max;
            $conditions[] = "sf_price <= $key";
        }
        if (!empty($conditions)) {
            $query['conditions'][] = '(' . implode(' AND ', $conditions) . ')';
        }
    }

    private function applyFlagFilters(array &$query, array $filters): void
    {
        if (!empty($filters['special_sale'])) {
            $query['conditions'][] = "special_sale = 1";
        }
        if (!empty($filters['has_price'])) {
            $query['conditions'][] = "sf_price > 0";
        }
    }

    private function getTotalCount(array $query): int
    {
        $sql = preg_replace('/ORDER BY.*|LIMIT.*/i', '', $query['sql']);
        $sql = "SELECT COUNT(*) FROM ($sql) as total";
        if ($this->debug) {
            error_log("Count SQL: " . $sql);
            error_log("Count Params: " . json_encode($query['params']));
        }
        $stmt = $this->conn->prepare($sql);
        foreach ($query['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function validateSort(string $sort): string
    {
        $allowed = ['sim_number', 'pre_number', 'sim_type', 'status', 'sf_price', 'special_sale', 'readable_numbers', 'last_update', 'featured'];
        return in_array($sort, $allowed, true) ? $sort : 'sf_price';
    }

    private function validateOrder(string $order): string
    {
        $order = strtolower($order);
        return in_array($order, ['asc', 'desc'], true) ? $order : 'asc';
    }
}
