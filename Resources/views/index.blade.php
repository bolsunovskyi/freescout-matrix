<form class="form-horizontal margin-top margin-bottom" method="POST" action="" id="matrixnotification_form">
    {{ csrf_field() }}

    <div class="form-group{{ $errors->has('settings.matrixnotification.active') ? ' has-error' : '' }} margin-bottom-10">
        <label for="matrixnotification.active" class="col-sm-2 control-label">{{ __('Active') }}</label>

        <div class="col-sm-6">
            <input id="matrixnotification.active" type="checkbox" class=""
                   name="settings[matrixnotification.active]"
                   @if (old('settings[matrixnotification.active]', $settings['matrixnotification.active']) == 'on') checked="checked" @endif
            />
        </div>
    </div>

    <div class="form-group{{ $errors->has('settings.matrixnotification.homeserver') ? ' has-error' : '' }} margin-bottom-10">
        <label for="matrixnotification.homeserver" class="col-sm-2 control-label">{{ __('Homeserver') }}</label>

        <div class="col-sm-6">
            <input id="matrixnotification.homeserver" type="text" class="form-control input-sized-lg"
                   name="settings[matrixnotification.homeserver]" value="{{ old('settings.matrixnotification.homeserver', $settings['matrixnotification.homeserver']) }}">
            @include('partials/field_error', ['field'=>'settings.matrixnotification.homeserver'])
        </div>
    </div>
    <div class="form-group{{ $errors->has('settings.matrixnotification.access_token') ? ' has-error' : '' }} margin-bottom-10">
        <label for="matrixnotification.access_token" class="col-sm-2 control-label">{{ __('Access Token') }}</label>

        <div class="col-sm-6">
            <input id="matrixnotification.access_token" type="text" class="form-control input-sized-lg"
                   name="settings[matrixnotification.access_token]" value="{{ old('settings.matrixnotification.access_token', $settings['matrixnotification.access_token']) }}">
        </div>
    </div>

    <div class="form-group{{ $errors->has('settings.matrixnotification.token_url') ? ' has-error' : '' }} margin-bottom-10">
        <label for="matrixnotification.room" class="col-sm-2 control-label">{{ __('Internal room ID') }}</label>

        <div class="col-sm-6">
            <input id="matrixnotification.room" type="text" class="form-control input-sized-lg"
                   name="settings[matrixnotification.room]" value="{{ old('settings.matrixnotification.room', $settings['matrixnotification.room']) }}">
        </div>
    </div>
    <div class="form-group">
        <label for="" class="col-sm-2 control-label">{{ __('Events') }}</label>

        <div class="col-sm-6">
            @foreach ($events as $event_code => $event_title)
                <div class="control-group">
                    <label class="checkbox" for="event_{{ $event_code }}">
                        <input type="checkbox" name="settings[matrixnotification.events][]" value="{{ $event_code }}" id="event_{{ $event_code }}" @if (in_array($event_code, old('settings[matrixnotification.events]', $settings['matrixnotification.events']))) checked="checked" @endif @if (!$active) disabled @endif> {{ $event_title }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="form-group margin-top margin-bottom">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">
                {{ __('Save') }}
            </button>
        </div>
    </div>
</form>