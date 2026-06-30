<x-layout 
    :title="__('ui.features.title')"
    description="Vote for the features you want to see implemented next on DevSense."
>
<div class="admin-container" style="max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; font-family: 'Inter', sans-serif;">
    
    <!-- Header Section -->
    <div style="text-align: center; margin-bottom: 3.5rem;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.75rem; font-weight: 800; background: linear-gradient(135deg, #fff, #93c5fd, #a5b4fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 1rem;">
            {{ __('ui.features.hero_title') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 600px; margin: 0 auto; line-height: 1.6;">
            {{ __('ui.features.hero_lead') }}
        </p>
    </div>

    <!-- Info & Voting Power Bar -->
    <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 3rem; backdrop-filter: blur(10px);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(99, 102, 241, 0.1); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                ⚡
            </div>
            <div>
                <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.1rem; color: var(--text-color);">
                    {{ __('ui.features.voting_power') }}
                </h4>
                <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--text-muted);">
                    {{ __('ui.features.voting_power_lead') }}
                </p>
            </div>
        </div>
        <div style="text-align: right;">
            @auth
                <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; font-family: 'Outfit', sans-serif;">
                    +{{ $userWeight }} {{ trans_choice('vote|votes', $userWeight) }}
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">
                    {{ __('ui.features.current_xp') . ' ' }}{{ number_format($user->points) }}
                </div>
            @else
                <div style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">
                    {{ __('ui.features.not_signed_in') }}
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                    <a href="{{ route('login.locale') }}" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                        {{ __('ui.features.sign_in_to_vote') }}
                    </a>
                </div>
            @endauth
        </div>
    </div>

    <!-- Features Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
        @foreach($features as $feature)
            @php
                $voted = $feature->votedBy($user);
            @endphp
            <div class="roadmap-card" data-feature-id="{{ $feature->id }}" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease; position: relative;"
                 onmouseenter="this.style.transform='translateY(-4px)'; this.style.borderColor='rgba(99, 102, 241, 0.4)'; this.style.boxShadow='0 10px 30px rgba(99, 102, 241, 0.05)';"
                 onmouseleave="this.style.transform='none'; this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
                
                <div>
                    <!-- Title -->
                    <h3 style="margin-top: 0; margin-bottom: 0.75rem; font-family: 'Outfit', sans-serif; font-size: 1.35rem; color: var(--text-color); font-weight: 700; line-height: 1.3;">
                        {{ $feature->title }}
                    </h3>
                    
                    <!-- Description -->
                    <p style="color: var(--text-muted); font-size: 0.925rem; line-height: 1.6; margin-bottom: 2rem; min-height: 60px;">
                        {{ $feature->description }}
                    </p>
                </div>

                <!-- Footer / Status & Action -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: auto; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1.25rem;">
                    <!-- Vote Stats -->
                    <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ __('ui.features.popularity') }}
                        </span>
                        <span style="font-size: 1.15rem; font-weight: 700; color: var(--text-color); font-family: 'Outfit', sans-serif;">
                            <span class="weight-count" style="color: #6366f1;">{{ number_format($feature->totalVotesWeight()) }}</span> 
                            <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);">
                                ({{ $feature->totalVotesCount() }} {{ __('ui.features.voters') }})
                            </span>
                        </span>
                    </div>

                    <!-- Vote Button -->
                    @auth
                        <button type="button" class="vote-btn {{ $voted ? 'voted' : '' }}" onclick="toggleVote({{ $feature->id }})" 
                                style="border: none; border-radius: 8px; padding: 0.6rem 1.25rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.25s ease; font-family: 'Outfit', sans-serif;">
                            {{ $voted ? (__('ui.features.retract_vote')) : (__('ui.features.vote')) }}
                        </button>
                    @else
                        <a href="{{ route('login.locale') }}" class="vote-btn-guest" 
                           style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-muted); border-radius: 8px; padding: 0.6rem 1.25rem; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-block; font-family: 'Outfit', sans-serif;">
                            {{ __('ui.features.vote') }}
                        </a>
                    @endauth
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
    .vote-btn {
        background: rgba(99, 102, 241, 0.1);
        color: #818cf8;
        border: 1px solid rgba(99, 102, 241, 0.3) !important;
    }
    .vote-btn:hover {
        background: rgba(99, 102, 241, 0.2);
        box-shadow: 0 0 15px rgba(99, 102, 241, 0.25);
    }
    .vote-btn.voted {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
        border: none !important;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    }
    .vote-btn.voted:hover {
        background: linear-gradient(135deg, #4f46e5, #4338ca);
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.6);
    }
    .vote-btn-guest:hover {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-color);
        border-color: var(--text-muted);
    }
</style>

<script>
function toggleVote(featureId) {
    const card = document.querySelector(`.roadmap-card[data-feature-id="${featureId}"]`);
    const btn = card.querySelector('.vote-btn');
    const weightLabel = card.querySelector('.weight-count');
    const votersLabel = weightLabel.nextElementSibling;

    btn.disabled = true;

    fetch(`/{{ app()->getLocale() }}/features/${featureId}/vote`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        }
    })
    .then(res => {
        if (!res.ok) {
            throw new Error("unauthorized");
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            // Toggle active state classes
            if (data.voted) {
                btn.classList.add('voted');
                btn.innerHTML = "{{ __('ui.features.retract_vote') }}";
            } else {
                btn.classList.remove('voted');
                btn.innerHTML = "{{ __('ui.features.vote') }}";
            }
            
            // Re-render counters
            weightLabel.innerText = data.total_weight.toLocaleString();
            votersLabel.innerText = `(${data.total_count} ${"{{ __('ui.features.voters') }}"})`;
        }
    })
    .catch(err => {
        console.error(err);
        window.location.href = "{{ route('login.locale') }}";
    })
    .finally(() => {
        btn.disabled = false;
    });
}
</script>
</x-layout>
