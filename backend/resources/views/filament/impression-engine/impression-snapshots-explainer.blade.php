<div class="fi-section mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
        How impression calculation works (plain language)
    </h3>

    <div class="mt-3 space-y-2 text-sm leading-6 text-gray-700 dark:text-gray-300">
        <p>
            This tool estimates how many people could have seen your ad by combining
            <strong>real GPS traces</strong> from campaign vehicles with a city-wide
            <strong>mobility reference dataset</strong> (traffic and pedestrian flow).
        </p>

        <p>
            For each GPS point, we place the vehicle into an H3 map cell and an hour bucket. Then we classify that bucket as
            driving or parking based on speed, apply audience coefficients, and sum hourly exposure into one snapshot.
        </p>

        <p>
            <strong>Example 1:</strong> if a wrapped vehicle drives through a high-traffic area in rush hour,
            the model takes that area flow and applies driving visibility shares and speed modifiers.
            <strong>Example 2:</strong> if the vehicle stays parked near a busy pedestrian street,
            the model uses parking shares and dwell-time modifiers for that hour.
        </p>

        <p>
            Snapshot status:
            <strong>queued</strong> (waiting in queue),
            <strong>processing</strong> (job is running),
            <strong>done</strong> (ready to use in API/reports),
            <strong>failed</strong> (check “Last error”).
        </p>
    </div>
</div>
