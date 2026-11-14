<?php
declare(strict_types=1);
require_once __DIR__ . '/CustomerAddressService.php';
require_once __DIR__ . '/PricingContextProvider.php';
require_once __DIR__ . '/DistanceWeightPrice.php';
require_once __DIR__ . '/PickupSchedule.php';

final class OrderQuoteService
{
    private PDO $db;
    private PricingContextProvider $provider;
    public function __construct(PDO $db, PricingContextProvider $provider)
    {
        $this->db = $db;
        $this->provider = $provider;
    }

    public function create(int $customerId, array $input): array
    {
        DeliveryInput::only($input, ['pickup', 'delivery', 'parcel', 'customer_note', 'currency_code', 'schedule']);
        $schedule = PickupSchedule::validate($input['schedule'] ?? null);
        if (array_key_exists('currency_code', $input) && !in_array($input['currency_code'], ['VND', 'USD'], true)) DeliveryInput::invalid('currency_code');
        $pickup = $this->point($customerId, $input['pickup'] ?? null);
        $delivery = $this->point($customerId, $input['delivery'] ?? null);
        if (!isset($input['parcel']) || !is_array($input['parcel'])) DeliveryInput::invalid('parcel');
        $parcel = $input['parcel'];
        DeliveryInput::only($parcel, ['category_id', 'description', 'weight_kg', 'length_cm', 'width_cm', 'height_cm']);
        $parcel['category_id'] = DeliveryInput::id($parcel['category_id'] ?? null, 'category_id');
        $this->requireCategory($parcel['category_id']);
        $parcel['weight_kg'] = DeliveryInput::decimal($parcel['weight_kg'] ?? null, 'weight_kg', 3, '0.001', '999999.999');
        $limits = require __DIR__ . '/../Config/delivery-pricing.php';
        if (bccomp($parcel['weight_kg'], $limits['max_weight_kg'], 3) > 0) {
            throw new DeliveryApiError(422, 'WEIGHT_NOT_SUPPORTED', 'Parcel weight exceeds the supported motorcycle limit.');
        }
        $parcel['description'] = DeliveryInput::text($parcel['description'] ?? null, 'description', 2000, false);
        foreach (['length_cm', 'width_cm', 'height_cm'] as $field) {
            $parcel[$field] = isset($parcel[$field]) ? DeliveryInput::decimal($parcel[$field], $field, 2, '0.01', '9999999.99') : null;
        }
        $note = DeliveryInput::text($input['customer_note'] ?? null, 'customer_note', 2000, false);
        $user = $this->db->prepare('SELECT app_language FROM users WHERE user_id = :id');
        $user->execute(['id' => $customerId]);
        $language = $user->fetchColumn();
        if (!in_array($language, ['vi', 'en'], true)) throw new DeliveryApiError(403, 'CUSTOMER_REQUIRED', 'Customer profile required.');
        $currency = $input['currency_code'] ?? ($language === 'vi' ? 'VND' : 'USD');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $q = $this->db->prepare('SELECT * FROM pricing_policies WHERE currency_code = :currency AND active AND effective_from <= :at AND (effective_until IS NULL OR effective_until > :until)');
        $q->execute(['currency' => $currency, 'at' => $now->format(DATE_ATOM), 'until' => $now->format(DATE_ATOM)]);
        $policy = $q->fetch();
        if (!$policy || !in_array($policy['calculation_rule'], ['DISTANCE_BAND_X_TIME_X_WEATHER', 'BASE_PLUS_DISTANCE_PLUS_WEIGHT'], true)) $this->unconfigured();
        if ($policy['calculation_rule'] === 'BASE_PLUS_DISTANCE_PLUS_WEIGHT' && bccomp($parcel['weight_kg'], (string) $policy['max_weight_kg'], 3) > 0) {
            throw new DeliveryApiError(422, 'WEIGHT_NOT_SUPPORTED', 'Parcel weight exceeds the supported policy limit.');
        }
        $context = $this->context($this->provider->resolve($pickup, $delivery, $now), $now);
        if (bccomp($context['distance_km'], $limits['max_distance_km'], 3) >= 0) {
            throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Delivery route is outside the supported distance range.');
        }
        if (isset($policy['max_distance_km']) && bccomp($context['distance_km'], (string) $policy['max_distance_km'], 3) >= 0) {
            throw new DeliveryApiError(422, 'OUTSIDE_SERVICE_AREA', 'Delivery route is outside the supported distance range.');
        }
        if ($policy['calculation_rule'] === 'BASE_PLUS_DISTANCE_PLUS_WEIGHT') {
            $priceComponents = DistanceWeightPrice::components($policy, $context['distance_km'], $parcel['weight_kg']);
            $subtotal = $priceComponents['subtotal_minor'];
        } else {
        $q = $this->db->prepare('SELECT * FROM distance_price_bands WHERE pricing_policy_id = :id AND min_km <= :distance AND (max_km IS NULL OR max_km > :upper_distance)');
        $q->execute(['id' => $policy['pricing_policy_id'], 'distance' => $context['distance_km'], 'upper_distance' => $context['distance_km']]);
        $distanceBands = $q->fetchAll();
        if (count($distanceBands) === 0) throw new DeliveryApiError(503, 'DISTANCE_PRICE_NOT_CONFIGURED', 'A price for this distance range has not been configured.');
        if (count($distanceBands) !== 1) $this->unconfigured();
        $distance = $distanceBands[0];
        $priceComponents = ['distance_band_id'=>$distance['distance_band_id'], 'distance_fee_minor'=>(string) $distance['amount_minor']];
        $subtotal = (string) $distance['amount_minor'];
        }
        $q = $this->db->prepare('SELECT * FROM time_price_bands WHERE pricing_policy_id = :id');
        $q->execute(['id' => $policy['pricing_policy_id']]);
        $local = $now->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        $timeBands = array_values(array_filter($q->fetchAll(), static fn(array $band): bool => self::timeMatches($band, $local)));
        if (count($timeBands) !== 1) $this->unconfigured();
        $time = $timeBands[0];
        $q = $this->db->prepare('SELECT * FROM weather_price_bands WHERE pricing_policy_id = :id AND provider_condition_code = :condition');
        $q->execute(['id' => $policy['pricing_policy_id'], 'condition' => $context['weather_condition']]);
        $weather = $q->fetch();
        if (!$weather) $this->unconfigured();
        $total = self::total($subtotal, (string) $time['multiplier'], (string) $weather['multiplier']);
        $feeQuery=$this->db->prepare('SELECT after_status,fee_minor FROM cancellation_fee_rules WHERE pricing_policy_id=?');
        $feeQuery->execute([$policy['pricing_policy_id']]);$cancellationFees=[];
        foreach($feeQuery->fetchAll() as $fee){if(bccomp((string)$fee['fee_minor'],$total)>0) $this->unconfigured();$cancellationFees[$fee['after_status']]=(string)$fee['fee_minor'];}
        $snapshot = [
            'schedule' => $schedule,
            'cancellation_fees' => $cancellationFees,
            'pickup' => $pickup, 'delivery' => $delivery, 'parcel' => $parcel, 'customer_note' => $note,
            'currency_code' => $currency, 'app_language' => $language,
            'pricing_policy_id' => $policy['pricing_policy_id'], 'pricing_policy_version' => $policy['config_version'],
            'calculation_rule' => $policy['calculation_rule'], 'priced_at' => $now->format(DATE_ATOM),
            'max_distance_km' => $policy['max_distance_km'] ?? null,
            'max_weight_kg' => $policy['max_weight_kg'] ?? null,
            'context' => $context,
            'components' => $priceComponents + [
                'time_band_id' => $time['time_band_id'], 'time_multiplier' => (string) $time['multiplier'],
                'weather_band_id' => $weather['weather_band_id'], 'weather_multiplier' => (string) $weather['multiplier'],
                'total_charge_minor' => $total, 'return_fee_minor' => (string) $policy['return_fee_minor'], 'cancellation_fee_before_assignment_minor' => '0'],
        ];
        $quoteId = bin2hex(random_bytes(16));
        $q = $this->db->prepare("INSERT INTO order_quotes (quote_id, customer_id, snapshot, expires_at) VALUES (:id, :owner, CAST(:snapshot AS jsonb), clock_timestamp() + interval '5 minutes') RETURNING expires_at");
        $q->execute(['id' => $quoteId, 'owner' => $customerId, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        return ['quote_id' => $quoteId, 'expires_at' => $q->fetchColumn(), 'snapshot' => $snapshot];
    }

    public function requireCategory(int $id): void
    {
        $q = $this->db->prepare("SELECT category_id FROM parcel_categories WHERE category_id = :id AND active AND restriction_type = 'ALLOWED' FOR SHARE");
        $q->execute(['id' => $id]);
        if (!$q->fetchColumn()) throw new DeliveryApiError(422, 'PARCEL_NOT_ALLOWED', 'Parcel category is unavailable, restricted or prohibited.');
    }

    public static function total(string $distanceFee, string $time, string $weather): string
    {
        $exact = bcmul(bcmul($distanceFee, $time, 7), $weather, 11);
        $floor = bcadd($exact, '0', 0);
        $total = bccomp($exact, $floor, 11) > 0 ? bcadd($floor, '1', 0) : $floor;
        if (bccomp($total, '9223372036854775807') > 0) throw new DeliveryApiError(503, 'PRICING_NOT_CONFIGURED', 'Calculated price exceeds supported limits.');
        return $total;
    }

    public static function timeMatches(array $band, DateTimeImmutable $local): bool
    {
        $clock = $local->format('H:i:s');
        $start = $band['starts_at_local']; $end = $band['ends_at_local'];
        $day = (int) $local->format('w');
        if ($start < $end) $inside = $clock >= $start && $clock < $end;
        else {
            $inside = $clock >= $start || $clock < $end;
            if ($clock < $end) $day = ($day + 6) % 7;
        }
        return $inside && ($band['day_of_week'] === null || (int) $band['day_of_week'] === $day);
    }

    private function point(int $owner, $value): array
    {
        if (!is_array($value)) DeliveryInput::invalid('pickup/delivery');
        DeliveryInput::only($value, ['saved_address_id', 'address', 'contact_name', 'contact_phone']);
        $id = null;
        if (isset($value['saved_address_id'])) {
            if (array_key_exists('address', $value)) DeliveryInput::invalid('address');
            $id = DeliveryInput::id($value['saved_address_id'], 'saved_address_id');
            $address = (new CustomerAddressService($this->db))->get($owner, $id);
        } else {
            if (!isset($value['address']) || !is_array($value['address'])) DeliveryInput::invalid('address');
            DeliveryInput::only($value['address'], ['label', 'country_code', 'province', 'district', 'ward', 'address_detail', 'latitude', 'longitude']);
            $address = DeliveryInput::address($value['address']);
        }
        $full = implode(', ', array_filter([$address['address_detail'], $address['ward'], $address['district'], $address['province']], static fn($v) => $v !== null && $v !== ''));
        if (mb_strlen($full) > 500) DeliveryInput::invalid('address');
        return ['saved_address_id' => $id, 'address' => $full, 'label' => $address['label'],
            'latitude' => $address['latitude'], 'longitude' => $address['longitude'], 'country_code' => 'VN',
            'contact_name' => DeliveryInput::text($value['contact_name'] ?? null, 'contact_name', 150),
            'contact_phone' => DeliveryInput::phone($value['contact_phone'] ?? null)];
    }

    private function context(array $data, DateTimeImmutable $now): array
    {
        try {
            $observed = DeliveryInput::text($data['observed_at'] ?? null, 'observed_at', 40);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $observed)) DeliveryInput::invalid('observed_at');
            $at = new DateTimeImmutable($observed);
            if (abs($now->getTimestamp() - $at->getTimestamp()) > 300) DeliveryInput::invalid('observed_at');
            $duration = DeliveryInput::id($data['duration_minutes'] ?? null, 'duration_minutes');
            if ($duration > 2147483647) DeliveryInput::invalid('duration_minutes');
            $extra = [];
            if (isset($data['weather_observed_at'])) {
                $weatherAt = new DateTimeImmutable($data['weather_observed_at']);
                if ($now->getTimestamp() - $weatherAt->getTimestamp() > 1800 || $weatherAt->getTimestamp() > $now->getTimestamp() + 60) DeliveryInput::invalid('weather_observed_at');
                $extra['weather_observed_at'] = $weatherAt->format(DATE_ATOM);
                if (isset($data['weather_code'])) $extra['weather_code'] = $data['weather_code'];
            }
            return ['distance_km' => DeliveryInput::decimal($data['distance_km'] ?? null, 'distance_km', 3, '0', '999999.999'),
                'duration_minutes' => $duration, 'weather_condition' => DeliveryInput::text($data['weather_condition'] ?? null, 'weather_condition', 80),
                'provider' => DeliveryInput::text($data['provider'] ?? null, 'provider', 100), 'observed_at' => $at->format(DATE_ATOM)] + $extra;
        } catch (Throwable $error) {
            throw new DeliveryApiError(503, 'PRICING_CONTEXT_UNAVAILABLE', 'Routing and weather provider returned invalid or stale data.');
        }
    }

    private function unconfigured(): void
    {
        throw new DeliveryApiError(503, 'PRICING_NOT_CONFIGURED', 'A complete and unambiguous pricing policy is required.');
    }
}
