# Timetrap To Harvest

This is a simple utility to transfer your times logged via timetrap to Harvest.
To set it up, copy the file ```example.timetrap-to-harvest.json``` to the
following location and fill in all values: ```~/.timetrap-to-harvest.json```.

If you install this package from source, you must also run
```composer install``` to install all dependencies. After that, you can
additionally symlink the ```timetrap-to-harvest.php``` executable to into a
```bin``` folder on your PATH.

## Time rounding
All times are automatically rounded to 15 minute blocks before transfer. Values
are rounded down when below 5 minutes and rounded up above. If a time entry is
small than 15 minutes, it is always rounded up.
