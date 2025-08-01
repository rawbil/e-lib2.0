<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Catalogue;
use App\Models\User;
use App\Models\Reservation; // NEW: Import the Reservation model
use App\Models\Loan; // NEW: Will be used for creating loans later
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // NEW: Import Carbon for date/time manipulation
use Illuminate\Support\Facades\Log; // NEW: Import Log for error logging

class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     * Accessible by authenticated librarians/admins.
     */
    public function dashboard()
    {
        return view('admin-views.dashboard');
    }

    /**
     * Display the manage books page.
     * Accessible by authenticated librarians/admins.
     */
    public function manageBooks(Request $request) // Added Request for filtering
    {
        // Fetch all books from the database with filtering and pagination
        $books = Catalogue::latest()
            ->filter($request->only(['search', 'tags', 'category'])) // Assuming filter method in Catalogue model
            ->paginate(10);

        // Pass the fetched books to the view
        return view('admin-views.manage-books', compact('books'));
    }

    /**
     * Handle the submission of the "Add New Book" form.
     * Stores the new book in the database.
     */
    public function storeBook(Request $request)
    {
        try {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:255',
                'isbn' => 'required|string|unique:catalogue,isbn|max:255', // Corrected table name to 'catalogue'
                'category' => 'required|string|max:255',
                'description' => 'required|string',
                'total_copies' => 'required|integer|min:0',
                'available_copies' => 'required|integer|min:0|lte:total_copies', // available_copies cannot exceed total_copies
                'published_year' => 'required|integer|min:1000|max:' . (date('Y') + 1), // Sensible range for years
                'tags' => 'required|string|max:255', // Ensure tags is a string
                'image' => 'nullable|url|max:2048',
            ]);

            // Create a new Book instance and fill it with validated data
            Catalogue::create($validatedData);

            // Redirect back to the manage books page with a success message
            return redirect()->route('admin.books')->with('success', 'Book added successfully!');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error adding book: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while adding the book. Please try again.')->withInput();
        }
    }

    /**
     * Handle the update of an existing book.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Catalogue  $book
     * @return \Illuminate\Http\Response
     */
    public function updateBook(Request $request, Catalogue $book)
    {
        try {
            $validatedData = $request->validate([
                'isbn' => 'required|string|unique:catalogue,isbn,' . $book->id . '|max:255', // Corrected table name to 'catalogue'
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'description' => 'required|string',
                'total_copies' => 'required|integer|min:0',
                'available_copies' => 'required|integer|min:0|lte:total_copies', // available_copies cannot exceed total_copies
                'published_year' => 'required|integer|min:1000|max:' . (date('Y') + 1),
                'tags' => 'required|string|max:255',
                'image' => 'nullable|url|max:2048',
            ]);

            $book->update($validatedData);

            return redirect()->route('admin.books')->with('success', 'Book updated successfully!');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error updating book: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating the book. Please try again.')->withInput();
        }
    }

    /**
     * Handle the deletion of a book.
     *
     * @param  \App\Models\Catalogue  $book
     * @return \Illuminate\Http\Response
     */
    public function destroyBook(Catalogue $book)
    {
        try {
            // Prevent deletion if there are active loans or pending reservations for this book
            if ($book->reservations()->where('status', 'pending')->exists()) {
                return redirect()->back()->with('error', 'Cannot delete book: There are pending reservations for this book.');
            }
            // Assuming you'll have a Loans model later
            // if ($book->loans()->whereNull('returned_at')->exists()) {
            //     return redirect()->back()->with('error', 'Cannot delete book: There are active loans for this book.');
            // }

            $book->delete();
            return redirect()->route('admin.books')->with('success', 'Book deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting book: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while deleting the book.')->withInput();
        }
    }


    /**
     * Display the manage loans page.
     * Accessible by authenticated librarians/admins.
     */
    public function manageLoans()
    {
        // This will be updated later when we implement the Loan model
        return view('admin-views.manage-loans');
    }

    /**
     * Display the manage reservations page.
     * Accessible by authenticated librarians/admins.
     */
    public function manageReservations(Request $request)
    {
        // Fetch all reservations with associated user and book details
        // Only show 'pending' and 'confirmed_pickup' reservations for active management
        $reservations = Reservation::with(['user', 'catalogue'])
                                ->whereIn('status', ['pending', 'confirmed_pickup'])
                                ->latest('reserved_at') // Order by most recent reservations first
                                ->paginate(10); // Paginate the results

        return view('admin-views.manage-reservations', compact('reservations'));
    }

    /**
     * Handle confirming a reservation pickup by a student.
     * This will transition a reservation to a loan.
     *
     * @param \App\Models\Reservation $reservation
     * @return \Illuminate\Http\Response
     */
    public function confirmReservationPickup(Reservation $reservation)
    {
        // Start a database transaction
        DB::beginTransaction();

        try {
            // Only confirm if the reservation is in 'pending' status
            if ($reservation->status !== 'pending') {
                DB::rollBack();
                return redirect()->back()->with('error', 'This reservation is not in a pending state and cannot be confirmed.');
            }

            // Check if the user has outstanding fee balances
            if ($reservation->user->fee_balance > 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Cannot confirm pickup: The student has an outstanding fee balance. Please clear their balance first.');
            }

            // Create a new Loan record (assuming Loan model exists and is imported)
            // This part will be fully implemented when we work on the Loan model
            // For now, we'll just update the reservation status.
            /*
            Loan::create([
                'user_id' => $reservation->user_id,
                'catalogue_id' => $reservation->catalogue_id,
                'borrowed_at' => Carbon::now(),
                'due_date' => Carbon::now()->addDays(14), // Example: 14 days loan period
                'status' => 'borrowed',
            ]);
            */

            // Update the reservation status to 'confirmed_pickup'
            $reservation->update(['status' => 'confirmed_pickup']);

            // No change to available_copies here, as it was decremented on reservation creation.
            // When the book is returned (via Loan management), available_copies will be incremented.

            DB::commit();
            return redirect()->route('admin.reservations')->with('success', 'Reservation confirmed and book marked as picked up.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error confirming reservation pickup: ' . $e->getMessage(), ['reservation_id' => $reservation->id]);
            return redirect()->back()->with('error', 'An error occurred while confirming pickup. Please try again.');
        }
    }

    /**
     * Handle cancelling a reservation.
     *
     * @param \App\Models\Reservation $reservation
     * @return \Illuminate\Http\Response
     */
    public function cancelReservation(Reservation $reservation)
    {
        // Start a database transaction
        DB::beginTransaction();

        try {
            // Only cancel if the reservation is in 'pending' status
            if ($reservation->status !== 'pending') {
                DB::rollBack();
                return redirect()->back()->with('error', 'This reservation cannot be cancelled as it is not in a pending state.');
            }

            // Update the reservation status to 'cancelled'
            $reservation->update(['status' => 'cancelled']);

            // Increment the available_copies for the book
            $book = $reservation->book; // Get the associated book
            if ($book) {
                $book->increment('available_copies');
            }

            DB::commit();
            return redirect()->route('admin.reservations')->with('success', 'Reservation cancelled successfully and book made available.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling reservation: ' . $e->getMessage(), ['reservation_id' => $reservation->id]);
            return redirect()->back()->with('error', 'An error occurred while cancelling the reservation. Please try again.');
        }
    }


    /**
     * Display the manage fines page.
     * Accessible by authenticated librarians/admins.
     */
    public function manageFines()
    {
        return view('admin-views.manage-fines');
    }

    /**
     * Display the manage members page.
     * Accessible by authenticated librarians/admins.
     */
    public function manageMembers(Request $request)
    {
        // Get search query from request
        $search = $request->input('search');

        // Fetch users with 'USR' utype (students)
        $query = User::where('utype', 'USR');

        // Apply search filter if a query is present
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('reg_number', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $members = $query->paginate(10); // Paginate the results

        return view('admin-views.manage-members', compact('members', 'search'));
    }

    /**
     * Handle the submission of the "Add New Member" form.
     * Creates a new User record with student details and generated password.
     */
    public function storeMember(Request $request)
    {
        try {
            // Validate the incoming request data
            $validatedData = $request->validate([
                'reg_number' => 'required|string|max:255|unique:users,reg_number',
                'full_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'fee_balance' => 'required|numeric|min:0',
            ], [], [
                'reg_number' => 'memberAdding',
                'full_name' => 'memberAdding',
                'email' => 'memberAdding',
                'fee_balance' => 'memberAdding',
            ]);

            $firstName = Str::before($validatedData['full_name'], ' ');
            $generatedPassword = $firstName . $validatedData['reg_number'];

            User::create([
                'name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($generatedPassword),
                'utype' => 'USR',
                'reg_number' => $validatedData['reg_number'],
                'fee_balance' => $validatedData['fee_balance'],
            ]);

            return redirect()->route('admin.members')->with('success', 'Member account created successfully! Initial password: ' . $generatedPassword);
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors(), 'memberAdding')
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Error adding member: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while adding the member: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Handle the update of an existing member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $member
     * @return \Illuminate\Http\Response
     */
    public function updateMember(Request $request, User $member)
    {
        try {
            $validatedData = $request->validate([
                'reg_number' => 'required|string|max:255|unique:users,reg_number,' . $member->id,
                'full_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $member->id,
                'fee_balance' => 'required|numeric|min:0',
            ], [], [
                'reg_number' => 'memberEditing',
                'full_name' => 'memberEditing',
                'email' => 'memberEditing',
                'fee_balance' => 'memberEditing',
            ]);

            $member->update([
                'name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'reg_number' => $validatedData['reg_number'],
                'fee_balance' => $validatedData['fee_balance'],
            ]);

            return redirect()->route('admin.members')->with('success', 'Member updated successfully!');
        } catch (ValidationException $e) {
            $request->session()->flash('editingMemberId', $member->id);
            return redirect()->back()
                ->withErrors($e->errors(), 'memberEditing')
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Error updating member: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating the member: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Handle the deletion of a member.
     *
     * @param  \App\Models\User  $member
     * @return \Illuminate\Http\Response
     */
    public function destroyMember(User $member)
    {
        try {
            if ($member->utype !== 'USR') {
                return redirect()->back()->with('error', 'Only student members can be deleted from this page.');
            }

            // Prevent deletion if the member has active loans or pending reservations
            if ($member->reservations()->where('status', 'pending')->exists()) {
                return redirect()->back()->with('error', 'Cannot delete member: They have pending book reservations.');
            }
            // Assuming you'll have a Loan model later
            // if ($member->loans()->whereNull('returned_at')->exists()) {
            //     return redirect()->back()->with('error', 'Cannot delete member: They have active book loans.');
            // }


            $member->delete();
            return redirect()->route('admin.members')->with('success', 'Member deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting member: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while deleting the member: ' . $e->getMessage());
        }
    }

    /**
     * Handle the import of members from a CSV file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importMembers(Request $request)
    {
        try {
            $request->validate([
                'import_file' => 'required|file|mimes:csv,txt|max:2048',
            ], [], [
                'import_file' => 'importingMembers',
            ]);

            $file = $request->file('import_file');
            $filePath = $file->getRealPath();

            $importedCount = 0;
            $failedCount = 0;
            $errors = [];

            if (($handle = fopen($filePath, 'r')) !== FALSE) {
                $header = fgetcsv($handle, 1000, ',');

                $columnMap = [
                    'full_name' => -1,
                    'email' => -1,
                    'reg_number' => -1,
                    'fee_balance' => -1,
                ];

                foreach ($header as $index => $colName) {
                    $cleanedColName = Str::snake(trim(strtolower($colName)));
                    if (array_key_exists($cleanedColName, $columnMap)) {
                        $columnMap[$cleanedColName] = $index;
                    }
                }

                foreach ($columnMap as $colName => $index) {
                    if ($index === -1) {
                        return redirect()->back()->with('error', "Missing required column in CSV: '{$colName}'. Please ensure your CSV has 'full_name', 'email', 'reg_number', 'fee_balance' columns.")->withInput();
                    }
                }

                $rowNumber = 1;
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $rowNumber++;
                    if (empty(array_filter($data))) {
                        continue;
                    }

                    $rowData = [
                        'full_name' => $data[$columnMap['full_name']] ?? null,
                        'email' => $data[$columnMap['email']] ?? null,
                        'reg_number' => $data[$columnMap['reg_number']] ?? null,
                        'fee_balance' => $data[$columnMap['fee_balance']] ?? null,
                    ];

                    DB::beginTransaction();
                    try {
                        $validator = \Illuminate\Support\Facades\Validator::make($rowData, [
                            'full_name' => 'required|string|max:255',
                            'email' => 'required|string|email|max:255|unique:users,email',
                            'reg_number' => 'required|string|max:255|unique:users,reg_number',
                            'fee_balance' => 'required|numeric|min:0',
                        ]);

                        if ($validator->fails()) {
                            $failedCount++;
                            $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                            DB::rollBack();
                            continue;
                        }

                        $firstName = Str::before($rowData['full_name'], ' ');
                        $generatedPassword = $firstName . $rowData['reg_number'];

                        User::create([
                            'name' => $rowData['full_name'],
                            'email' => $rowData['email'],
                            'password' => Hash::make($generatedPassword),
                            'utype' => 'USR',
                            'reg_number' => $rowData['reg_number'],
                            'fee_balance' => $rowData['fee_balance'],
                        ]);

                        $importedCount++;
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $failedCount++;
                        $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                    }
                }
                fclose($handle);
            } else {
                return redirect()->back()->with('error', 'Could not open the uploaded file.')->withInput();
            }

            $message = "Import complete! Successfully imported {$importedCount} members.";
            if ($failedCount > 0) {
                $message .= " Failed to import {$failedCount} members.";
                return redirect()->route('admin.members')->with('warning', $message)->with('importErrors', $errors);
            }

            return redirect()->route('admin.members')->with('success', $message);

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors(), 'importingMembers')
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Error importing members: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred during import: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * Display the add librarian page.
     * Accessible by authenticated super admins.
     */
    public function addLibrarian()
    {
        return view('admin-views.add-librarian');
    }

    /**
     * Display the create student account page.
     * Accessible by authenticated super admins.
     */
    public function createStudentAccount()
    {
        return view('admin-views.create-student-account');
    }
}
