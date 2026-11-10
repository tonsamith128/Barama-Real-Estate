<label>Rental deposit <input name="deposit_amount" type="number" min="0" step=".01" value="{{ old('deposit_amount', $listing?->deposit_amount) }}"></label>
<label>Available from <input name="available_from" type="date" value="{{ old('available_from', $listing?->available_from?->format('Y-m-d')) }}"></label>
<label>Pricing period <select name="pricing_period">
    <option value="">Not specified</option>
    @foreach(['month'=>'Monthly','week'=>'Weekly','day'=>'Daily','year'=>'Yearly','one_time'=>'One time'] as $code=>$label)
        <option value="{{ $code }}" @selected(old('pricing_period', $listing?->pricing_period) === $code)>{{ $label }}</option>
    @endforeach
</select></label>