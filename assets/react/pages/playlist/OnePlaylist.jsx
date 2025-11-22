import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams , useLocation } from "react-router-dom";
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
  const location = useLocation();

  const [loading, setLoading] = useState(true);
  const [playlist, setPlaylist] = useState(null);
  const [error, setError] = useState(null);
  const [currentTrack, setCurrentTrack] = useState(null);
  const [playingId, setPlayingId] = useState(null);
  const audioRef = useRef(new Audio());
  const [deleting, setDeleting] = useState(false);
  const [alert, setAlert] = useState({ type: "", message: "" });
  const [isFavorite, setIsFavorite] = useState(location.state?.isFavorite ?? false);
  const [favoriteLoading, setFavoriteLoading] = useState(false);

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

                const item = Array.isArray(data?.["hydra:member"]) && data["hydra:member"][0] ? data["hydra:member"][0] : data;

                const genres = Array.isArray(item.genres) ? item.genres : (Array.isArray(item.seedGenres) ? item.seedGenres : []);

                const tracks = Array.isArray(item.tracks) ? item.tracks.map((t) => {
                    const coverUrl = t.coverUrl ?? t.image_url ?? (t.album && t.album.images && t.album.images[0] ? t.album.images[0].url : null) ?? "/images/track-placeholder.png";
                    const artistsArr = Array.isArray(t.artists) && t.artists.length ? t.artists : (Array.isArray(t.artistNames) && t.artistNames.length ? t.artistNames : (t.artist ? [t.artist] : []));

                    let durationMs = null;
                    if (Number.isFinite(t.duration_ms)) {
                        durationMs = t.duration_ms;
                    } else if (Number.isFinite(t.duration)) {
                        durationMs = t.duration > 1000 ? t.duration : Math.round(t.duration * 1000);
                    }

                    return {
                        id: t.id ?? t.spotifyId ?? t.spotify_id ?? null,
                        title: t.title ?? t.name ?? "",
                        artists: artistsArr,
                        album: t.album ?? t.album_name ?? "",
                        coverUrl,
                        durationMs,
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

                if (typeof item.isFavorite === "boolean") {
                    setIsFavorite(item.isFavorite);
                }
                else if (typeof location.state?.isFavorite === "boolean") {
                    setIsFavorite(location.state.isFavorite);
                }
                else {
                    setIsFavorite(false);
                }
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
            duration: Number.isFinite(t.durationMs) ? Math.round(t.durationMs / 1000) : null,
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

    const onDelete = async () => {
        if (!playlist?.id) {
            setAlert({
                type: "error",
                message: "Impossible de supprimer : playlist sans identifiant.",
            });
            return;
        }

        if (!window.confirm("Confirmer la suppression de cette playlist ? Cette action est irréversible.")) {
            return;
        }

        setDeleting(true);
        setAlert({ type: "", message: "" });

        try {
            const res = await apiService.delete(`/api/me/playlists/${playlist.id}`);

            console.log("[OnePlaylist] DELETE /api/me/playlists/", playlist.id, "status:", res.status);

            if (res.status === 200 || res.status === 204) {
                navigate("/history", {
                    state: {
                        message: "Playlist supprimée avec succès",
                        type: "success",
                    },
                });
            } else {
                setAlert({
                    type: "error",
                    message: `Impossible de supprimer la playlist (HTTP ${res.status}).`,
                });
                setDeleting(false);
            }
        } catch (err) {
            console.error("[OnePlaylist] delete error", err);
            const msg =
                err?.response?.data?.message ??
                err?.message ??
                "Erreur réseau lors de la suppression.";
            setAlert({ type: "error", message: msg });
            setDeleting(false);
        }
    };

    const handleFavoriteClick = async () => {
        if (!playlist?.id || favoriteLoading) return;

        setFavoriteLoading(true);

        try {
            const API_URL = process.env.REACT_APP_API_URL || "";
            const prefix = API_URL.endsWith("/api") ? "" : "/api";

            if (!isFavorite) {
                await apiService.post(`${prefix}/playlist/add-to-favorite`, {targetId: playlist.id,});
                setIsFavorite(true);
            } else {
                await apiService.delete(`${prefix}/${"playlist"}/${playlist.id}/delete-to-favorite`);
                setIsFavorite(false);
            }
        } catch (e) {
            console.error("Favorite toggle error", e);
        } finally {
            setFavoriteLoading(false);
        }
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
            <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 gap-4">
                <MenuAside />

                <section className="md:col-span-3 lg:col-span-4 col-span-2 w-full max-w-4xl h-full p-0 md:px-2 px-4 overflow-y-auto">
                    <div className="md:max-h-[calc(100vh-6rem)] overflow-y-auto p-4 md:p-8 pb-24">
                        <div className="max-w-6xl mx-auto">
                            <div className="flex items-center md:justify-between md:flex-row mb-6 flex-col">
                                <div className="flex items-center gap-6 flex-col md:flex-row">
                                    <img src={cover} alt={playlist.title || "Playlist cover"} className="w-20 h-20 md:w-28 md:h-28 object-cover rounded shadow"/>
                                    <div>
                                        <h1 className="text-2xl md:text-3xl font-bold text-center md:text-left">
                                            {playlist.title || "Playlist générée"}
                                        </h1>
                                        <div className="mt-2 flex flex-wrap justify-center gap-2 mb-8">
                                            {playlist.mood ?
                                                <div className="badge badge-primary">
                                                    mood: {playlist.mood}
                                                </div>
                                            : null}
                                            {playlist.activity ?
                                                <div className="badge badge-secondary">
                                                    activity: {playlist.activity}
                                                </div>
                                            : null}
                                            {playlist.trackCount ?
                                                <div className="badge">
                                                    tracks: {playlist.trackCount}
                                                </div>
                                            : null}
                                        </div>
                                    </div>
                                </div>

                                <div className="w-48">
                                    {!isFavorite ? (
                                        <button className="btn bg-black text-white hover:bg-black/80 border-0 mb-4 w-full" onClick={handleFavoriteClick} disabled={favoriteLoading}>
                                            {favoriteLoading ? "Ajout en cours..." : "Ajouter aux favoris"}
                                        </button>
                                    ) : (
                                        <button className="btn bg-red-600 text-black hover:bg-red-700 border-0 mb-4 w-full" onClick={handleFavoriteClick} disabled={favoriteLoading}>
                                            {favoriteLoading ? "Retrait en cours..." : "Retirer des favoris"}
                                        </button>
                                    )}
                                    <button type="button" className={`btn btn-error w-full ${deleting ? "loading" : ""}`} onClick={onDelete} disabled={deleting}>
                                        {deleting ? "Suppression…" : "Supprimer la playlist"}
                                    </button>
                                </div>
                            </div>

                            <div className="space-y-3 w-full">
                                {Array.isArray(playlist.tracks) && playlist.tracks.length > 0 ? (
                                    playlist.tracks.map((track) => (
                                        <div key={track.id || track.spotifyId} className="card bg-white border-2 border-black hover:bg-base-200 cursor-pointer transition-all rounded-2xl w-full" onClick={() => handleSelectForPlayer(track)}>
                                            <div className="card-body p-4 flex flex-row md:items-center items-start gap-4 md:gap-2">
                                                <div className="avatar">
                                                    <div className="w-12 h-12 rounded bg-base-200 overflow-hidden">
                                                        <img src={track.coverUrl || "/images/track-placeholder.png"} alt={track.title || "cover"}/>
                                                    </div>
                                                </div>

                                                <div className="flex-1 min-w-0 text-left">
                                                    <h3 className="font-medium">
                                                        {track.title || "Titre inconnu"}
                                                    </h3>
                                                    <p className="text-sm text-base-content/70">
                                                        {Array.isArray(track.artists) && track.artists.length ? track.artists.join(", ") : "Artiste inconnu"}
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
