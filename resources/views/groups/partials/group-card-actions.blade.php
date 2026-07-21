{{-- Shared by both the trending and main grids on groups/index.blade.php so
     the join/leave/view-forum logic can't drift between the two lists. --}}
@if(auth()->user()->Role === 'Administrator')
    <a href="{{ route('groups.forum', $group->GroupID) }}" class="btn btn-primary" style="width: 100%; justify-content: center;">
        View Forum <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
    </a>
@elseif($group->userJoined)
    <form method="POST" action="{{ route('groups.leave', $group->GroupID) }}" onsubmit="return confirm('Leave this group? You will lose access to its topics and chat.');" style="width: 100%;">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger" style="width: 100%; justify-content: center;">
            Leave Group <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
        </button>
    </form>
@else
    <form method="POST" action="{{ route('groups.join', $group->GroupID) }}" style="width: 100%;">
        @csrf
        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
            Join Group <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
        </button>
    </form>
@endif
