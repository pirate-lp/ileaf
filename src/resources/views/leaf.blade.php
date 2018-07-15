@extends('ileaf::master')

@push('title') {{ $leaf['title'] }} | {{ $base['title'] }} @endpush

@if ( array_key_exists('description', $leaf) )
	@push('description'){{ $leaf['description'] }}@endpush
@endif

@section('cssclass')
	@if(array_key_exists('template',$leaf))
		{{ $leaf['template'] }}
	@endif
	@if (isset($base))
		@if(array_key_exists('template',$base))
			{{ $base['template'] }}
		@endif
	@endif
@endsection

@section('body')

	@section('main')

		{!! $leaf['content'] !!}

	@show

@endsection



		