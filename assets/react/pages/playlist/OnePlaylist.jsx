import {useEffect, useMemo, useRef, useState} from "react";
import {useNavigate, useParams} from "react-router-dom";
import Header from "@components/Header";
import MenuAside from "@components/MenuAside";
import apiService from "@services/apiService";

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

  const [playingId, setPlayingId] = useState(null);
  const audioRef = useRef(new Audio());

  useEffect(() => {
    let isMounted = true;

    async function fetchPlaylist() {
      setLoading(true);
      setError(null);
      try {
        // Compute correct API prefix based on configured base URL
        const API_URL = process.env.REACT_APP_API_URL || '';
        const prefix = API_URL.endsWith('/api') ? '' : '/api';
        const url = `${prefix}/me/playlists/${id}`;
        console.log("[OnePlaylist] API_URL =", API_URL, "→ url =", url);
        const { data } = await apiService.get(url);
        if (!isMounted) return;
        console.log("[OnePlaylist] raw response", data);

        // Normalize tracks shape so UI always works
        const normalized = {
          ...data,
          tracks: Array.isArray(data.tracks)
            ? data.tracks.map((t) => ({
                id: t.id ?? t.spotifyId ?? t.spotify_id ?? null,
                title: t.title ?? t.name ?? "",
                artist: t.artist ?? (Array.isArray(t.artists) ? t.artists.join(", ") : ""),
                album: t.album ?? t.album_name ?? "",
                coverUrl:
                  t.coverUrl ??
                  t.image_url ??
                  (t.album && t.album.images && t.album.images[0] ? t.album.images[0].url : null) ??
                  "/images/track-placeholder.png",
                // duration in seconds (backend may return duration or duration_ms)
                duration: t.duration ?? (t.duration_ms ? Math.round(t.duration_ms / 1000) : undefined),
                preview_url: t.preview_url ?? null,
                spotifyId: t.spotifyId ?? t.spotify_id ?? null,
              }))
            : [],
        };

        console.log("[OnePlaylist] normalized", normalized);
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

  const handlePlayPreview = (track) => {
    if (!track.preview_url) return;
    const audio = audioRef.current;

    // Toggle play/pause if same track
    if (playingId === track.id) {
      audio.pause();
      setPlayingId(null);
      return;
    }

    try {
      audio.pause();
      audio.src = track.preview_url;
      audio.currentTime = 0;
      audio.play().then(() => setPlayingId(track.id));
      audio.onended = () => setPlayingId(null);
    } catch (e) {
      console.error("[OnePlaylist] audio error", e);
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

  return (
    <>
      <Header />
      <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
        <MenuAside />
        <section className="lg:col-span-4 sm:grid-cols-2 h-full p-6">
          {/* Playlist header */}
          <div className="flex items-center gap-6 mb-6">
            <img
              src={cover}
              alt={playlist.title || "Playlist cover"}
              className="w-28 h-28 object-cover rounded shadow"
            />
            <div className="flex-1">
              <h1 className="text-2xl font-semibold">{playlist.title || "Playlist"}</h1>
              {playlist.description ? (
                <p className="text-sm opacity-80 mt-1">{playlist.description}</p>
              ) : null}
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

          {/* Tracks */}
          <div className="overflow-x-auto">
            <table className="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cover</th>
                  <th>Title / Artist</th>
                  <th>Album</th>
                  <th>Duration</th>
                  <th className="text-right">Preview</th>
                </tr>
              </thead>
              <tbody>
                {Array.isArray(playlist.tracks) && playlist.tracks.length > 0 ? (
                  playlist.tracks.map((t, idx) => (
                    <tr key={t.id || t.spotifyId || t.spotify_id || idx}>
                      <td>{idx + 1}</td>
                      <td>
                        <img
                          src={t.coverUrl || "/images/track-placeholder.png"}
                          alt={t.title}
                          className="w-12 h-12 rounded object-cover"
                        />
                      </td>
                      <td>
                        <div className="font-medium">{t.title}</div>
                        <div className="text-sm opacity-70">{t.artist}</div>
                      </td>
                      <td className="opacity-80">{t.album || "-"}</td>
                      <td className="opacity-80">{t.duration ? msToMinSec(t.duration * 1000) : "-"}</td>
                      <td className="text-right">
                        {t.preview_url ? (
                          <button
                            className={`btn btn-xs ${playingId === t.id ? "btn-error" : "btn-outline"}`}
                            onClick={() => handlePlayPreview(t)}
                          >
                            {playingId === t.id ? "Stop" : "Play"}
                          </button>
                        ) : (
                          <span className="opacity-60 text-xs">No preview</span>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6}>
                      <div className="alert mt-2">No tracks in this playlist yet.</div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </>
  );
}