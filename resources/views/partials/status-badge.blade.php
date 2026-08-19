{{-- Lencana status rekaman. Butuh variabel Alpine `item` di lingkupnya. --}}
<span class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-medium"
      :class="{
          'bg-sunken text-ink-soft': item.status === 'pending',
          'bg-accent-soft text-accent': item.status === 'processing',
          'bg-positive/12 text-positive': item.status === 'completed',
          'bg-danger-soft text-danger': item.status === 'failed',
          'bg-caution/12 text-caution': item.status === 'cancelled',
      }">
    <span class="size-1.5 rounded-full"
          :class="{
              'bg-ink-faint': item.status === 'pending',
              'bg-accent animate-pulse': item.status === 'processing',
              'bg-positive': item.status === 'completed',
              'bg-danger': item.status === 'failed',
              'bg-caution': item.status === 'cancelled',
          }"
          aria-hidden="true"></span>
    <span x-text="item.status_label"></span>
</span>
