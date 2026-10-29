<div>
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Accounts') }}</h1>
    <p class="mt-1 text-sm text-stone-600">{{ __('Organizers can create events. The main admin manages accounts and can see all events.') }}</p>

    @if ($status)
        <div role="status" class="mt-6 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            {{ $status }}
        </div>
    @endif

    <section class="mt-6 rounded-lg border border-stone-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('New account') }}</h2>

        <form wire:submit="create" class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
            <div>
                <label for="name" class="block text-sm font-medium">{{ __('Name') }}</label>
                <input id="name" type="text" wire:model="name" required
                    class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none">
                @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                <input id="email" type="email" wire:model="email" required
                    class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none">
                @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="role" class="block text-sm font-medium">{{ __('Role') }}</label>
                <select id="role" wire:model="role"
                    class="mt-1 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 shadow-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none">
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800 focus:ring-2 focus:ring-teal-600/40 focus:outline-none">
                <span wire:loading.remove wire:target="create">{{ __('Create account') }}</span>
                <span wire:loading wire:target="create">{{ __('Creating…') }}</span>
            </button>
        </form>
    </section>

    <div class="mt-6 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Last login') }}</th>
                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" @class(['text-stone-400' => ! $user->is_active])>
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">{{ $user->role->label() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $user->last_login_at?->isoFormat('lll') ?? '–' }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-800">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Deactivated') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($user->id === auth()->id())
                                <span class="text-xs text-stone-500">{{ __('You') }}</span>
                            @else
                                <button type="button" wire:click="toggleActive({{ $user->id }})" class="text-stone-700 underline-offset-2 hover:underline">
                                    {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                </button>
                                <button type="button" wire:click="delete({{ $user->id }})"
                                    wire:confirm="{{ __('Delete the account for :email? This cannot be undone.', ['email' => $user->email]) }}"
                                    class="ml-3 text-red-700 underline-offset-2 hover:underline">
                                    {{ __('Delete') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
