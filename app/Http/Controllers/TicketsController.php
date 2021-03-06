<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Input;
use App\Ticket;
use App\Location;
use App\Tag;
use App\Category;
use App\VehicleClass;

class TicketsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('tickets.index')->with('tickets', Ticket::paginate(20));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $ticket = new Ticket();
        $ticket->edit_count = -1;
        $ticket->signature = $request['signature'];
        $ticket->image = $request['signature'] . '.jpg';
        $ticket->thumb = $request['signature'] . '.jpg'; // @HACK

        $ticket->point_of_departure_id = $request['point_of_departure_id'];
        $ticket->destination_id = $request['destination_id'];

        // not working? ignoring image for now
        if(!Input::hasFile('image')) {
            return new JsonResponse('url encoded image required', 422);
        }

        $file = Input::file('image');
        $file->move(public_path('img/tickets'), $file->getClientOriginalName());

        $this->update($request, $ticket);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        dd('show ticket');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('tickets.edit', [
        'ticket' => Ticket::findOrFail($id),
        'locations' => Location::orderBy('name')->get(),
        'categories' => Category::orderBy('name')->get(),
        'vehicleClasses' => VehicleClass::all(),
        // 'tags' => Tag::orderBy('name')->get()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Ticket  $ticket
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Ticket $ticket)
    {
        $ticket->description = $request['description'];
        $ticket->category_id = $request['category_id'];
        $ticket->vehicle_class_id = $request['vehicle_class_id'];
        $ticket->price = $request['price'];
        $ticket->edit_count += 1;

        // check point of departure
        if ($request['point_of_departure_name'] && $request['point_of_departure_latitude'] && $request['point_of_departure_longitude']) {
            $latitude = round($request['point_of_departure_latitude'], 5);
            $longitude = round($request['point_of_departure_longitude'], 5);
            $pointOfDeparture = Location::
                where('latitude', $latitude)
                ->where('longitude', $longitude)->first();
            if ($pointOfDeparture == null) {
                $pointOfDeparture = new Location();
                $pointOfDeparture->name = $request['point_of_departure_name'];
                $pointOfDeparture->latitude = $latitude;
                $pointOfDeparture->longitude = $longitude;
                $pointOfDeparture->save();
            }
            $ticket->point_of_departure_id = $pointOfDeparture->id;
        }

        // check destination
        if ($request['destination_name'] && $request['destination_latitude'] && $request['destination_longitude']) {
            $latitude = round($request['destination_latitude'], 5);
            $longitude = round($request['destination_longitude'], 5);
            $destination = Location::
                where('latitude', $latitude)
                ->where('longitude', $longitude)->first();
            if ($destination == null) {
                $destination = new Location();
                $destination->name = $request['destination_name'];
                $destination->latitude = $latitude;
                $destination->longitude = $longitude;
                $destination->save();
            }
            $ticket->destination_id = $destination->id;
        }

        if ($request->has('points')) {
            $points = $request['points'];
            session()->put('points', $points + 1);
            // save points for logged in user
            if (auth()->check()) {
                $user = auth()->user();
                $user->points = $points + 1;
                $user->save();
            }
        }

        if ($request['ocr_text']) {
            $ticket->ocrText = $request['ocr_text'];
        }

        $ticket->save();

        // keep track of edited tickets in session
        session()->push('edited_ticket_ids', $ticket->id);

        if ($request['redirect'] == 'back') {
            return back();
        }

        return redirect(route('tickets.index'));
    }

    /**
     * Reset a ticket's edited values; tickets cannot be properly deleted.
     *
     * @param  \App\Ticket  $ticket
     * @return \Illuminate\Http\Response
     */
    public function destroy(Ticket $ticket)
    {
        $ticket->point_of_departure_id = null;
        $ticket->destination_id = null;
        $ticket->description = null;
        $ticket->category_id = null;
        $ticket->vehicle_class_id = null;
        $ticket->price = null;
        $ticket->edit_count = 0;

        $ticket->save();

        return redirect(route('tickets.index'));
    }
}
