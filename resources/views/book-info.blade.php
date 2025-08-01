@php
    $tagsArray = explode(',', $book->tags);

    //check if book is already reserved by user
    $isReserved = \App\Models\Reservation::where('user_id', auth()->id())
        ->where('catalogue_id', $book->id)
        ->where('status', 'pending')
        ->whereNotIn('status', ['cancelled', 'expired'])
        ->exists();
@endphp

@extends('layouts.home')
@section('content')
    <x-home-sections
        class="bg-light text-dark pb-4 py-4 flex items-center justify-around max-600px:flex-col max-600px:gap-20 gap-[5%]">
        @if ($book)
            <img src={{ $book->image ? $book->image : 'https://ukombozilibrary.org/wp-content/uploads/2021/05/book-placeholder.jpg' }}
                alt="book" title='{{ $book->title }} by {{ $book->author }}'
                class="w-[300px] max-600px:w-[90%] h-[70%] object-fit rounded shadow shadow-dark">
            <article class="text-dark flex flex-col gap-4">
                <h2><span class="">Title: </span><span class="font-bold text-xl">{{ $book->title }}</span></h2>
                <h3><span>Author: </span><span class="font-medium text-xl">{{ $book->author }}</span></h3>
                <p>Description: <span class="font-medium">{{ $book->description }}</span></p>
                <div class="flex gap-4">
                    <p>Total Copies: <span class="font-medium">{{ $book->total_copies }}</span></p>
                    <p>Books Available: <span class="font-medium">{{ $book->available_copies }}</span></p>
                </div>

                <p>Published: <span class="font-medium">{{ $book->published_year }}</span></p>
                {{-- Tags --}}
                <div class="flex gap-3 max-600px:justify-center">
                    @foreach ($tagsArray as $tag)
                        <a href="/library-catalogue?tag={{ $tag }}"
                            class="bg-dark text-light p-1 rounded shadow shadow-dark/80 hover:bg-dark/80 transition-all">{{ $tag }}</a>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-5 max-600px:flex-col">
                    @if ($book->available_copies)
                        @if ($isReserved)
                            <div class="flex gap-4 max-600px:flex-col">
                                <button disabled
                                    class="bg-gray-500 text-white mt-5 block text-center p-2 rounded w-[200px] max-600px:w-full cursor-not-allowed">
                                    Reserved
                                </button>
                                <form action="/cancel-reservation/{{ $book->id }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="bg-red-600 text-white mt-5 block text-center p-2 rounded hover:bg-red-700 transition-all w-[200px] max-600px:w-full">
                                        Cancel
                                    </button>
                                </form>
                            </div>
                        @else
                            <form action="/borrow/{{ $book->id }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="bg-dark text-light mt-5 block text-center p-2 rounded hover:text-light/90 hover:bg-dark/90 transition-all w-[200px] max-600px:w-full ">
                                    Borrow
                                </button>
                            </form>
                        @endif
                    @else
                        <button
                            class="bg-dark/50 mt-5 cursor-not-allowed disabled:true block text-center p-2 rounded transition-all w-1/2 max-600px:w-full">Checked
                            out
                        </button>
                    @endif
                </div>

            </article>
        @else
            <p class="text-red-600 font-bold">This book is not available (it may have been deleted).</p>
        @endif

    </x-home-sections>
@endsection
