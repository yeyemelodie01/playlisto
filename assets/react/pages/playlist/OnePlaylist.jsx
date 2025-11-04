import {useEffect, useMemo, useRef, useState} from "react";
import {useNavigate, useParams} from "react-router-dom";
import Header from "@components/Header";
import MenuAside from "@components/MenuAside";
import apiService from "@services/apiService";
import MusicPlayer from "@components/MusicPlayer";

function msToMinSec(ms) {
    const totalSec = Math.floor(ms / 1000);
    const min = Math.floor(totalSec / 60);
    const sec = totalSec % 60;
    return `${min}:${sec.toString().padStart(2, "0")}`;
}

export default function OnePlaylist() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [playlist, setPlaylist] = useState(null);
  const [error, setError] = useState(null);
  const [currentTrack, setCurrentTrack] = useState(null);
  const [playingId, setPlayingId] = useState(null);
  const audioRef = useRef(new Audio());

    useEffect(() => {
        let isMounted = true;

        async function fetchPlaylist() {
            setLoading(true);
            setError(null);
            try {
                const API_URL = process.env.REACT_APP_API_URL || '';
                const prefix = API_URL.endsWith('/api') ? '' : '/api';
                const url = `${prefix}/me/playlists/${id}`;
                const { data } = await apiService.get(url);
                if (!isMounted) return;

                console.log("[OnePlaylist] raw payload keys:", Object.keys(data));
                const item = Array.isArray(data?.["hydra:member"]) && data["hydra:member"][0]
                    ? data["hydra:member"][0]
                    : data;

                console.log("[OnePlaylist] using item:", item);

                const genres = Array.isArray(item.genres)
                    ? item.genres
                    : (Array.isArray(item.seedGenres) ? item.seedGenres : []);

                const tracks = Array.isArray(item.tracks) ? item.tracks.map((t) => {
                    const coverUrl =
                        t.coverUrl ??
                        t.image_url ??
                        (t.album && t.album.images && t.album.images[0] ? t.album.images[0].url : null) ??
                        "/images/track-placeholder.png";

                    // --- ARTISTS: tente plusieurs sources ---
                    const artistsArr = Array.isArray(t.artists) && t.artists.length
                        ? t.artists
                        : (Array.isArray(t.artistNames) && t.artistNames.length
                            ? t.artistNames
                            : (t.artist ? [t.artist] : []));

                    // --- DURATION: normalise en millisecondes ---
                    let durationMs = null;
                    if (Number.isFinite(t.duration_ms)) {
                        durationMs = t.duration_ms;
                    } else if (Number.isFinite(t.duration)) {
                        // si t.duration > 1000 on suppose que c'est déjà en ms, sinon en secondes
                        durationMs = t.duration > 1000 ? t.duration : Math.round(t.duration * 1000);
                    }

                    return {
                        id: t.id ?? t.spotifyId ?? t.spotify_id ?? null,
                        title: t.title ?? t.name ?? "",
                        artists: artistsArr,
                        album: t.album ?? t.album_name ?? "",
                        coverUrl,
                        durationMs, // ✅ on stocke en ms
                        previewUrl: (t.previewUrl ?? t.preview_url ?? null) || null,
                        spotifyId: t.spotifyId ?? t.spotify_id ?? null,
                    };
                }) : [];

                const normalized = {
                    id: item.id,
                    title: item.title ?? "Playlist générée",
                    description: item.description ?? "",
                    mood: item.mood ?? null,
                    activity: item.activity ?? null,
                    createdAt: item.createdAt ?? null,
                    trackCount: item.trackCount ?? tracks.length,
                    genres,
                    tracks,
                };

                setPlaylist(normalized);
            } catch (e) {
                console.error("[OnePlaylist] fetch error", e);
                if (!isMounted) return;
                setError(e?.response?.data ?? { message: e.message });
            } finally {
                if (isMounted) setLoading(false);
            }
        }

        fetchPlaylist();

        return () => {
            isMounted = false;
            try {
                audioRef.current.pause();
                audioRef.current.src = "";
            } catch {}
        };
    }, [id]);

    const cover = useMemo(() => {
        if (!playlist?.tracks?.length) return "/images/playlist-placeholder.png";
        return playlist.tracks[0]?.coverUrl || "/images/playlist-placeholder.png";
    }, [playlist]);

    const handleSelectForPlayer = (t) => {
        console.log("[handleSelectForPlayer] Track clicked:", t);
        console.log("[handleSelectForPlayer] artists:", t.artists, "previewUrl:", t.previewUrl);

        setCurrentTrack({
            id: t.id || t.spotifyId,
            title: t.title || "Titre inconnu",
            artists: Array.isArray(t.artists) && t.artists.length ? t.artists.join(", ") : "Artiste inconnu",
            image: t.coverUrl || "/images/track-placeholder.png",
            previewUrl: t.previewUrl || null,
            duration: Number.isFinite(t.durationMs) ? Math.round(t.durationMs / 1000) : null, // en secondes si tu veux
        });
    };

    const handlePrev = () => {
        if (!playlist?.tracks?.length || !currentTrack) return;
        const arr = playlist.tracks;
        const idx = arr.findIndex(t => (t.id || t.spotifyId) === currentTrack.id);
        const prev = arr[(idx - 1 + arr.length) % arr.length];
        if (prev) handleSelectForPlayer(prev);
    };

    const handleNext = () => {
        if (!playlist?.tracks?.length || !currentTrack) return;
        const arr = playlist.tracks;
        const idx = arr.findIndex(t => (t.id || t.spotifyId) === currentTrack.id);
        const next = arr[(idx + 1) % arr.length];
        if (next) handleSelectForPlayer(next);
    };

    if (loading) {
        return (
            <>
                <Header />
                <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                    <MenuAside />
                    <section className="lg:col-span-4 sm:col-span-2 h-full p-6">
                        <div className="flex items-center gap-4">
                            <div className="skeleton h-24 w-24 rounded"></div>
                            <div className="flex-1">
                                <div className="skeleton h-6 w-1/3 mb-3"></div>
                                <div className="skeleton h-4 w-1/2"></div>
                            </div>
                        </div>
                        <div className="mt-6 space-y-2">
                            {[...Array(6)].map((_, i) => (
                                <div key={i} className="skeleton h-12 w-full"></div>
                            ))}
                        </div>
                    </section>
                </main>
            </>
        );
    }

    if (error) {
        return (
            <>
                <Header />
                <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                    <MenuAside />
                    <section className="lg:col-span-4 sm:col-span-2 h-full p-6">
                        <div className="alert alert-error">
                            <span>Failed to load playlist: {error.message || "Unknown error"}</span>
                        </div>
                        <button className="btn btn-outline mt-4" onClick={() => navigate(-1)}>
                            ← Back
                        </button>
                    </section>
                </main>
            </>
        );
    }

    if (!playlist) return null;

    const genres = Array.isArray(playlist.genres) ? playlist.genres : [];

    return (
        <>
            <Header />
            <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />

                <section className="lg:col-span-4 sm:col-span-2 h-full p-0">
                    <div className="min-h-[calc(100vh-3.5rem-5rem)] p-4 md:p-8 pb-24">
                        <div className="max-w-6xl mx-auto">
                            <div className="flex items-center justify-between mb-6">
                                <div className="flex items-center gap-6">
                                    <img
                                        src={cover}
                                        alt={playlist.title || "Playlist cover"}
                                        className="w-20 h-20 md:w-28 md:h-28 object-cover rounded shadow"
                                    />
                                    <div>
                                        <h1 className="text-2xl md:text-3xl font-bold">
                                            {playlist.title || "Playlist générée"}
                                        </h1>
                                        <div className="mt-2 flex flex-wrap gap-2 items-center">
                                            {playlist.mood ? <div className="badge badge-primary">mood: {playlist.mood}</div> : null}
                                            {playlist.activity ? <div className="badge badge-secondary">activity: {playlist.activity}</div> : null}
                                            {playlist.trackCount ? <div className="badge">tracks: {playlist.trackCount}</div> : null}
                                            {playlist.createdAt ? (
                                                <div className="badge badge-ghost">
                                                    {new Date(playlist.createdAt).toLocaleString()}
                                                </div>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                <button className="btn bg-black text-white hover:bg-black/80 border-0">
                                    Ajouter aux favoris
                                </button>
                            </div>

                            <div className="flex flex-wrap gap-2 mb-8">
                                {genres.map((genre) => (
                                    <div
                                        key={genre}
                                        className="badge badge-lg border-2 border-[#9333EA] text-[#9333EA] bg-[#F3E8FF] hover:bg-[#9333EA] hover:text-white cursor-pointer transition-all py-4 px-4 rounded-full"
                                    >
                                        {genre}
                                    </div>
                                ))}
                            </div>

                            <div className="space-y-3">
                                {Array.isArray(playlist.tracks) && playlist.tracks.length > 0 ? (
                                    playlist.tracks.map((track) => (
                                        <div
                                            key={track.id || track.spotifyId}
                                            className="card bg-white border-2 border-black hover:bg-base-200 cursor-pointer transition-all rounded-2xl"
                                            onClick={() => handleSelectForPlayer(track)}
                                        >
                                            <div className="card-body p-4 flex-row items-center gap-4">
                                                <div className="avatar">
                                                    <div className="w-12 h-12 rounded bg-base-200 overflow-hidden">
                                                        <img
                                                            src={track.coverUrl || "/images/track-placeholder.png"}
                                                            alt={track.title || "cover"}
                                                        />
                                                    </div>
                                                </div>

                                                <div className="flex-1 min-w-0">
                                                    <h3 className="font-medium truncate">
                                                        {track.title || "Titre inconnu"}
                                                    </h3>
                                                    <p className="text-sm text-base-content/70 truncate">
                                                        {Array.isArray(track.artists) && track.artists.length
                                                            ? track.artists.join(", ")
                                                            : "Artiste inconnu"}
                                                    </p>
                                                </div>

                                                <div className="hidden md:block text-sm text-base-content/70">
                                                    {track.album || ""}
                                                </div>

                                                <div className="hidden md:block text-sm text-base-content/70">
                                                    {Number.isFinite(track.durationMs)
                                                        ? msToMinSec(track.durationMs)
                                                        : "-"}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="alert">No tracks in this playlist yet.</div>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <MusicPlayer
                track={currentTrack}
                onPrev={handlePrev}
                onNext={handleNext}
            />
        </>
  );
}