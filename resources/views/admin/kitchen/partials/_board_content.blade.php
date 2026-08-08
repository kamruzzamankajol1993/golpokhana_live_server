<style>
  .progga-kitchen-summary-category {
    border: 1px solid rgba(255,255,255,.16);
    border-radius: 12px;
    background: rgba(255,255,255,.075);
    padding: 10px 10px 8px;
    margin-bottom: 11px;
    box-shadow: 0 8px 22px rgba(0,0,0,.10);
  }
  .progga-kitchen-summary-category-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,.12);
  }
  .progga-kitchen-summary-category-name {
    color: #fff !important;
    font-size: 13px;
    font-weight: 900;
    line-height: 1.25;
  }
  .progga-kitchen-summary-category-total {
    background: #ffc107;
    color: #1f2937;
    border-radius: 999px;
    padding: 4px 9px;
    font-size: 13px;
    font-weight: 900;
    min-width: 34px;
    text-align: center;
  }
  .progga-kitchen-summary-food-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 7px 0 0 10px;
  }
  .progga-kitchen-summary-food-name {
    color: rgba(255,255,255,.88) !important;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.25;
  }
  .progga-kitchen-summary-food-qty {
    background: rgba(255,255,255,.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,.16);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 900;
  }
</style>

<div class="progga-kitchen-summary-col">
    <div class="progga-kitchen-col-head progga-kitchen-col-head--summary">
      <i class="bi bi-list-columns-reverse"></i>
      <span>Food Summary</span>
      <span class="progga-kitchen-badge progga-kitchen-badge--gold progga-kitchen-col-count">{{ $totalSummaryItems ?? 0 }}</span>
    </div>
    <div class="progga-kitchen-summary-body" id="kitchenSummaryBody">
        @forelse(($categorySummary ?? []) as $categoryName => $categoryData)
            <div class="progga-kitchen-summary-category">
                <div class="progga-kitchen-summary-category-head">
                    <span class="progga-kitchen-summary-category-name">{{ $categoryName }}</span>
                    <span class="progga-kitchen-summary-category-total">{{ $categoryData['total'] ?? 0 }}</span>
                </div>

                @foreach(($categoryData['items'] ?? []) as $foodName => $foodQty)
                    <div class="progga-kitchen-summary-food-row">
                        <span class="progga-kitchen-summary-food-name">{{ $foodName }}</span>
                        <span class="progga-kitchen-summary-food-qty">{{ $foodQty }}</span>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="text-muted text-center mt-4">No active foods</div>
        @endforelse
    </div>
</div>

<div class="progga-kitchen-col" id="pending-col">
    <div class="progga-kitchen-col-head progga-kitchen-col-head--pending">
      <i class="bi bi-hourglass-split"></i>
      <span>Pending</span>
      <span class="progga-kitchen-badge progga-kitchen-badge--warning progga-kitchen-col-count">{{ $kots->where('kitchen_status', 'Pending')->count() }}</span>
    </div>
    <div class="progga-kitchen-col-body">
        @foreach($kots->where('kitchen_status', 'Pending') as $kot)
            @include('admin.kitchen.partials._single_kot_card', ['kot' => $kot, 'actionLabel' => 'Start Cooking', 'nextStatus' => 'Cooking', 'btnClass' => 'start', 'icon' => 'play-fill'])
        @endforeach
    </div>
</div>

<div class="progga-kitchen-col" id="cooking-col">
    <div class="progga-kitchen-col-head progga-kitchen-col-head--cooking">
      <i class="bi bi-fire"></i>
      <span>Cooking</span>
      <span class="progga-kitchen-badge progga-kitchen-badge--info progga-kitchen-col-count">{{ $kots->where('kitchen_status', 'Cooking')->count() }}</span>
    </div>
    <div class="progga-kitchen-col-body">
        @foreach($kots->where('kitchen_status', 'Cooking') as $kot)
            @include('admin.kitchen.partials._single_kot_card', ['kot' => $kot, 'actionLabel' => 'Mark Ready', 'nextStatus' => 'Ready', 'btnClass' => 'ready', 'icon' => 'check-circle-fill'])
        @endforeach
    </div>
</div>

<div class="progga-kitchen-col" id="ready-col">
    <div class="progga-kitchen-col-head progga-kitchen-col-head--ready">
      <i class="bi bi-bell-fill"></i>
      <span>Ready to Serve</span>
      <span class="progga-kitchen-badge progga-kitchen-badge--success progga-kitchen-col-count">{{ $kots->where('kitchen_status', 'Ready')->count() }}</span>
    </div>
    <div class="progga-kitchen-col-body">
        @foreach($kots->where('kitchen_status', 'Ready') as $kot)
            @include('admin.kitchen.partials._single_kot_card', ['kot' => $kot, 'actionLabel' => 'Mark Delivered', 'nextStatus' => 'Delivered', 'btnClass' => 'deliver', 'icon' => 'bag-check-fill'])
        @endforeach
    </div>
</div>
