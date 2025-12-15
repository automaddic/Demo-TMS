<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '/home/automaddic/mtb/server/config/bootstrap.php';



// server config and functions
$tz = new DateTimeZone('America/New_York');
$now = new DateTime('now', $tz);
$today = new DateTime('today', $tz);
$tomorrow = (clone $today)->modify('+1 day');
$twoWeeks = (clone $today)->modify('+14 days');

// Get latest end_datetime today
$stmtLatest = $pdo->prepare("
  SELECT start_datetime
  FROM practice_days 
  WHERE DATE(start_datetime) = ?
");
$stmtLatest->execute([$today->format('Y-m-d')]);
$latestEnd = $stmtLatest->fetchColumn();

// Determine lower bound for the query
if ($latestEnd) {
    $startAfter = (new DateTime($latestEnd, $tz))->modify('-1 hour');
} else {
    $startAfter = new DateTime('now', $tz); // fallback if no practices today
}


$stmt = $pdo->prepare("
  SELECT * FROM practice_days 
  WHERE start_datetime >= ? AND start_datetime <= ?
  ORDER BY start_datetime ASC
");
$stmt->execute([
    $startAfter->format('Y-m-d H:i:s'),
    $twoWeeks->format('Y-m-d H:i:s')
]);
$practices = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Sope Creek MTB</title>
    <link rel="stylesheet" href="styles/home.css">
</head>

<body>
    <?php if ($user['is_suspended']): ?>
        <div class="day-modal-overlay" style="display: flex;">
            <div class="day-modal-content">
                <h2>You're Account is On The Way!</h2>
                <p>Your account is currently under review by a coach because your name isn't one of our registered riders or coaches.</p>
                <p>You will be notified once your account is approved!</p>
            </div>
        </div>
    <?php endif; ?>
    <div class="content-wrapper <?= $user['is_suspended'] ? 'blurred' : '' ?>">
        <?php include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php"; ?>

        <div class="page-wrapper">
            <?php
            // Load prefill values from session if set
            $prefill = $_SESSION['profile_form'] ?? [
                'first_name' => '',
                'last_name' => '',
                'preferred_name' => '',
                'ride_group_id' => '',
                'wants_texts' => 0,
                'wants_emails' => 0,
                'phone_number' => '',
            ];
            ?>

            <?php if (!$user['is_profile_complete'] && !$user['is_suspended']): ?>
                <div id="setup-modal" class="day-modal-overlay"
                    style="display: <?= isset($_SESSION['suspension_pending']) ? 'none' : 'flex' ?>;">
                    <div class="day-modal-content">
                        <h2>Complete Your Profile</h2>
                        <form action="router-api.php?path=api/setup-profile.php" method="POST">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($prefill['first_name']) ?>"
                                required>

                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($prefill['last_name']) ?>"
                                required>

                            <label>Preferred Name</label>
                            <input type="text" name="preferred_name"
                                value="<?= htmlspecialchars($prefill['preferred_name']) ?>">

                            <!-- <label>Ride Group</label>
                            <select name="ride_group_id" required>
                                <?php foreach ($rideGroups as $group): ?>
                                    <option value="<?= $group['id'] ?>" <?= $prefill['ride_group_id'] == $group['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($group['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select> -->

                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="wants_texts" id="wants_texts"
                                        <?= !empty($prefill['wants_texts']) ? 'checked' : '' ?>>
                                    Receive Text Updates
                                </label>

                                <div id="phone-wrapper">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="tel" name="phone_number" id="phone_number"
                                        value="<?= htmlspecialchars($prefill['phone_number'] ?? '') ?>"
                                        <?= !empty($prefill['wants_texts']) ? 'required' : '' ?>>
                                </div>


                                <label>
                                    <input type="checkbox" name="wants_emails" <?= !empty($prefill['wants_emails']) ? 'checked' : '' ?>>
                                    Receive Email Updates
                                </label>
                            </div>

                            <script>
                                const wantsTexts = document.getElementById('wants_texts');
                                const phoneWrapper = document.getElementById('phone-wrapper');
                                const phoneInput = document.getElementById('phone_number');

                                // Function to update visibility and required state
                                function updatePhoneVisibility() {
                                    if (wantsTexts.checked) {
                                        phoneWrapper.style.display = 'block';
                                        phoneInput.required = true;
                                    } else {
                                        phoneWrapper.style.display = 'none';
                                        phoneInput.required = false;
                                    }
                                }

                                // Run on page load
                                updatePhoneVisibility();

                                // Run whenever checkbox changes
                                wantsTexts.addEventListener('change', updatePhoneVisibility);
                            </script>



                            <input type="hidden" name="force_save" id="force_save" value="0">

                            <button type="submit">Save</button>
                        </form>
                    </div>
                </div>



            <?php endif; ?>




            <?php if (isset($_SESSION['suspension_pending'])): ?>
                <div id="suspend-warning" class="day-modal-overlay" style="display: flex;">
                    <div class="day-modal-content">
                        <h2>Un-Registered Name</h2>
                        <p>Your name doesn't match any registered riders in our system.</p>
                        <p>If you continue, your account will be reviewed by a coach to ensure our participants safety. We hope you understand.</p>
                        <button onclick="goBackToProfile()">Go Back</button>
                        <button onclick="proceedAnyway()" style="background-color: #706e66 !important;">Continue</button>
                    </div>
                </div>

                <script>
                    function goBackToProfile() {
                        document.getElementById('suspend-warning').style.display = 'none';
                        document.getElementById('setup-modal').style.display = 'flex';

                    }

                    function proceedAnyway() {
                        document.getElementById('force_save').value = "1";
                        document.querySelector('#setup-modal form').submit();
                    }
                </script>
                <!-- Do NOT unset suspension_pending here; let setup-profile.php do it -->
            <?php endif; ?>


            <?php if (isset($_SESSION['suspension_pending']) || (!$user['is_profile_complete'] && !$user['is_suspended']) || ($user['is_suspended'])): ?>
                <script>document.body.classList.add('modal-open');
                </script>
            <?php else: ?>
                <script>document.body.classList.remove('modal-open');
                </script>
            <?php endif; ?>
            <div class="header-wrapper">
                <div class="header-content">
                    <div class="welcome-wrapper">
                        <p id="welcome">Welcome,<br><span><?= htmlspecialchars($user['username']) ?></span></p>
                    </div>
                    <img src="images/sope-200.png" alt="Logo" class="header-logo">
                </div>
            </div>

            <section class="container">
                <div id="weather-section">
                    <div id="weather-loading" class="mini-modal">
                        <div class="mini-modal-content">
                            Loading weather...
                        </div>
                    </div>
                    <div id="weather-section-content" style="display: none;">
                        <h2>Today's Weather</h2>
                        <img id="weather-icon" src="" alt="Weather Icon" style="display: none;" />
                        <p><strong>Forecast:</strong> <span id="weather-main">Loading...</span></p>
                        <p><strong>Temperature:</strong> <span id="weather-temp">--</span>°F</p>
                        <p><strong>Heat Index:</strong> <span id="weather-feels">--</span>°F</p>
                        <p><strong>Humidity:</strong> <span id="weather-humidity">--</span>%</p>
                    </div>
                </div>
                <div class="practices-container">
                    <h2>Upcoming Events</h2>
                    <div class="practices-scroll">
                        <?php foreach ($practices as $pd):
                            $start = new DateTime($pd['start_datetime'], $tz);
                            $end = !empty($pd['end_datetime']) ? new DateTime($pd['end_datetime'], $tz) : null;
                            $now = new DateTime('now', $tz);

                            $isOngoing = $end !== null && $now >= $start && $now <= $end;
                            $graceEnd = $end ? (clone $end)->modify('+1 hour') : null;
                            $isRecentlyEnded = $end && $now > $end && $now <= $graceEnd;
                            $hideCompletely = $graceEnd && $now > $graceEnd;
                            $pastCutoff = $now > (clone $start)->modify('+10 minutes');

                            if ($end && $hideCompletely) continue;

                            $panelClass = 'practice-panel';
                            if ($isOngoing) $panelClass .= ' ongoing';
                            if ($isRecentlyEnded) $panelClass .= ' recently-ended';
                        ?>
                            <div class="<?= $panelClass ?>" data-id="<?= $pd['id'] ?>" data-end-time="<?= $end ? $end->format('c') : '' ?>">
                                <h3><?= htmlspecialchars($pd['name']) ?></h3>
                                <div class="datetime">
                                    <?= $start->format('M j, Y g:ia') ?> 
                                    <?php if (!empty($end)): ?>
                                        &ndash; <?= $end->format('g:ia') ?>
                                    <?php endif; ?>
                                </div>
                                <div class="location">Location: <?= htmlspecialchars($pd['location'] ?? '') ?></div>
                                <div class="panel-footer">
                                    <?php if (!empty($pd['map_link'])): ?>
                                        <a class="btn-directions" href="<?= htmlspecialchars($pd['map_link']) ?>" target="_blank" rel="noopener">Directions</a>
                                    <?php endif; ?>
                                    <button class="btn-enlarge" title="Enlarge"><img src="<?= $baseUrl ?>/public/images/icons/enlarge.png"></button>
                                </div>
                                <div class="attendance-buttons">
                                    <p style="color: white; margin: 1px !important;">RSVP:</p>
                                    <button class="attend-yes" <?= $pastCutoff ? 'disabled' : '' ?>>Yes</button>
                                    <button class="attend-maybe" <?= $pastCutoff ? 'disabled' : '' ?>>Maybe</button>
                                    <button class="attend-no" <?= $pastCutoff ? 'disabled' : '' ?>>No</button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </section>
        </div>
    </div>
    <!-- Enlarge Modal -->
    <div id="modal-enlarge" class="day-modal-overlay" style="display:none;">
        <div class="modal-content-attend enlarge-modal-content">
            <button id="modal-close-enlarge" class="modal-close">&times;</button>
            <div id="enlarge-container">
                <!-- Populated dynamically -->
                <h2 id="enlarge-name"></h2>
                <p id="enlarge-datetime"></p>
                <p id="enlarge-location"></p>
                <p><a id="enlarge-directions" href="#" target="_blank" rel="noopener noreferrer">Get Directions</a></p>
                <div id="enlarge-daytype" style="margin-bottom:1rem;"></div>
                <hr>
                <div id="enlarge-weather">
                    <!-- Weather info populated -->
                    <h3>Weather</h3>
                    <div id="weather-content">Loading...</div>
                </div>
                <hr>
                <div id="enlarge-notes">
                    <h3>Coach Notes for Your Ride Group</h3>
                    <div id="notes-content">Loading...</div>
                    <!-- Optionally allow adding a note here if desired -->
                </div>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.practice-panel.recently-ended').forEach(panel => {
            // Get end time from data attribute or hidden span if you store it
            const endTime = new Date(panel.dataset.endTime); // if you pass this in
            const removalTime = new Date(endTime.getTime() + 60 * 60 * 1000); // +1 hour

            const now = new Date();
            const msUntilRemove = removalTime - now;

            if (msUntilRemove > 0) {
                setTimeout(() => {
                    panel.remove();
                }, msUntilRemove);
            }
        });
    </script>

    <script>
        function safeFetchWeather() {
            fetch("router-api.php?path=api/compile/get-weather.php")
                .then(res => res.json())
                .then(weather => {
                    if (weather && weather.main !== 'Unavailable') {
                        document.getElementById('weather-main').textContent = weather.main;
                        document.getElementById('weather-temp').textContent = weather.temp;
                        document.getElementById('weather-feels').textContent = weather.feels_like;
                        document.getElementById('weather-humidity').textContent = weather.humidity;

                        const iconEl = document.getElementById('weather-icon');
                        iconEl.src = `https://openweathermap.org/img/wn/${weather.icon}@2x.png`;
                        iconEl.style.display = 'inline';
                        document.getElementById('weather-loading').style.display = 'none';
                        document.getElementById('weather-section-content').style.display = 'block';
                    } else {
                        document.getElementById('weather-main').textContent = "Unavailable";
                        document.getElementById('weather-loading').style.display = 'none';
                        document.getElementById('weather-section-content').style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error("Weather fetch error:", err);
                    document.getElementById('weather-main').textContent = "Unavailable";
                    document.getElementById('weather-loading').style.display = 'none';
                    document.getElementById('weather-section-content').style.display = 'block';
                });
        }



        function saveUserLocationAndFetchWeather() {
            // Always request location fresh each time
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    sendLocationToServer(lat, lon);
                    safeFetchWeather();
                }, error => {
                    console.error("Geolocation error:", error);
                    safeFetchWeather(); // fallback if denied
                });
            } else {
                console.warn("Geolocation not supported");
                safeFetchWeather();
            }
        }

        function sendLocationToServer(lat, lon) {
            const now = new Date();
            const localDate = now.toISOString().split('T')[0];
            const localHour = now.getHours();

            fetch("router-api.php?path=api/save-user-location.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ lat, lon, date: localDate, hour: localHour })
            })
            .then(res => res.json())
            .then(data => console.log("Location saved", data))
            .catch(err => console.error("Location error", err));
        }

        document.addEventListener("DOMContentLoaded", () => {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(saveUserLocationAndFetchWeather);
            } else {
                setTimeout(saveUserLocationAndFetchWeather, 500);
            }
        });





        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('modal-enlarge');
            const btnClose = document.getElementById('modal-close-enlarge');
            const enlargeName = document.getElementById('enlarge-name');
            const enlargeDatetime = document.getElementById('enlarge-datetime');
            const enlargeLocation = document.getElementById('enlarge-location');
            const enlargeDirections = document.getElementById('enlarge-directions');
            const enlargeDaytype = document.getElementById('enlarge-daytype');
            const weatherContent = document.getElementById('weather-content');
            const notesContent = document.getElementById('notes-content');


            // Open modal when clicking enlarge button
            document.querySelectorAll('.btn-enlarge').forEach(btn => {
                btn.addEventListener('click', async (ev) => {
                    const panel = ev.target.closest('.practice-panel');
                    document.body.classList.add('modal-open');

                    if (!panel) return;
                    const pdId = panel.dataset.id;
                    // Show modal
                    modal.style.display = 'flex';
                    // Clear previous
                    enlargeName.textContent = 'Loading...';
                    enlargeDatetime.textContent = '';
                    enlargeLocation.textContent = '';
                    enlargeDirections.href = '#';
                    enlargeDaytype.textContent = '';
                    weatherContent.textContent = 'Loading...';
                    notesContent.textContent = 'Loading...';

                    try {
                        const res = await fetch('router-api.php?path=api/compile/get-practice-day-details.php&id=' + encodeURIComponent(pdId));
                        if (!res.ok) throw new Error('Network response was not OK');
                        const data = await res.json();

                        // Populate fields
                        enlargeName.textContent = data.name;

                        // Format datetime nicely:
                        const start = new Date(data.start_datetime);

                        // Force English output regardless of user's browser language
                        const optionsDate = { year: 'numeric', month: 'short', day: 'numeric' };
                        const optionsTime = { hour: 'numeric', minute: '2-digit', hour12: true };

                        const dateStr = start.toLocaleDateString('en-US', optionsDate);
                        const startStr = start.toLocaleTimeString('en-US', optionsTime);

                        // Handle nullable end time
                        let endStr = '';
                        if (data.end_datetime) {
                            const end = new Date(data.end_datetime);
                            endStr = end.toLocaleTimeString('en-US', optionsTime);
                        }

                        enlargeDatetime.textContent = endStr
                            ? `${dateStr} • ${startStr} - ${endStr}`
                            : `${dateStr} • ${startStr}`;

                        enlargeLocation.textContent = data.location || '';


                        if (data.map_link) {
                            enlargeDirections.href = data.map_link;
                            enlargeDirections.style.display = 'inline';
                        } else {
                            enlargeDirections.style.display = 'none';
                        }
                        if (data.day_type_name) {
                            enlargeDaytype.textContent = 'Type: ' + data.day_type_name;
                        }
                        // Weather
                        if (data.weather) {
                         weatherContent.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="https://openweathermap.org/img/wn/${data.weather.icon}@2x.png" alt="Weather Icon" style="width: 60px; height: 60px;">
                                <div style="line-height: 1.4;">
                                <div style="font-weight: bold; font-size: 1.2em;">${data.weather.main}</div>
                                <div>${data.weather.temp}°F (feels like ${data.weather.feels_like}°F)</div>
                                <div>Humidity: ${data.weather.humidity}%</div>
                                </div>
                            </div>
                            `;

                        } else {
                            weatherContent.textContent = 'No weather data.';
                        }
                        // Notes
                        if (Array.isArray(data.notes) && data.notes.length) {
                            notesContent.innerHTML = data.notes.map(n =>
                                `<p><strong>${n.coach_name}:</strong> ${n.notes}</p>`
                            ).join('');
                        } else {
                            notesContent.textContent = 'No notes for your ride group yet.';
                        }
                    } catch (err) {
                        console.error(err);
                        enlargeName.textContent = 'Error loading details.';
                        weatherContent.textContent = '';
                        notesContent.textContent = '';
                    }
                });
            });

            // Close modal
            btnClose.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');

            });
            // Close when clicking outside content
            modal.addEventListener('click', (ev) => {
                if (ev.target === modal) {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');

                }
            });

            document.querySelectorAll('.attendance-buttons button').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const panel = e.target.closest('.practice-panel');
                    const pdId = panel.dataset.id;
                    const response = e.target.classList.contains('attend-yes') ? 'yes'
                        : e.target.classList.contains('attend-maybe') ? 'maybe'
                            : 'no';

                    

                    try {
                        const res = await fetch('router-api.php?path=api/save-attendance.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ practice_day_id: pdId, response })
                        });

                        const data = await res.json();
                        

                        if (data.success) {
                            alert('Attendance saved');
                        } else {
                            alert('Failed: ' + data.error);
                        }
                    } catch (err) {
                        alert('Error saving attendance.');
                        console.error(err);
                    }
                });
            });


        });
    </script>




</body>

</html>
