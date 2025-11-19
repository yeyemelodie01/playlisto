import { Play, Pause, SkipBack, SkipForward } from "lucide-react";
import {useEffect, useMemo, useRef, useState} from "react";

function formatTime(sec) {
    if (!Number.isFinite(sec)) return "0:00";
    const m = Math.floor(sec / 60);
    const s = Math.max(0, Math.floor(sec % 60));
    return `${m}:${s.toString().padStart(2, "0")}`;
}

const MusicPlayer = ({ track, onPrev, onNext }) => {
    const audioRef = useRef(null);
    const [isPlaying, setIsPlaying] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);

    const isPlayable = useMemo(() => !!(track && track.previewUrl), [track]);

    useEffect(() => {
        const audio = audioRef.current;
        if (!audio) return;

        setIsPlaying(false);
        setCurrentTime(0);

        if (track?.previewUrl) {
            audio.src = track.previewUrl;
            audio.load();
        } else {
            audio.removeAttribute("src");
            audio.load();
        }

        const apiDur = Number(track?.duration);
        const normalized = Number.isFinite(apiDur) ? (apiDur > 600 ? Math.round(apiDur / 1000) : apiDur) : 0;
        setDuration(normalized > 0 ? normalized : 0);
    }, [track]);

    useEffect(() => {
        const audio = audioRef.current;
        if (!audio) return;

        const onTime = () => setCurrentTime(audio.currentTime || 0);
        const onMeta = () => {
            const fromAudio = Number(audio.duration) || 0;

            const apiDur = Number(track?.duration);
            const normalizedApi = Number.isFinite(apiDur) ? (apiDur > 600 ? Math.round(apiDur / 1000) : apiDur) : 0;

            const next = normalizedApi > 0 ? normalizedApi : fromAudio;
            setDuration(next > 0 ? next : 0);
        };
        const onEnd = () => {
            setIsPlaying(false);
            if (onNext) onNext();
        };

        audio.addEventListener("timeupdate", onTime);
        audio.addEventListener("loadedmetadata", onMeta);
        audio.addEventListener("ended", onEnd);

        return () => {
            audio.removeEventListener("timeupdate", onTime);
            audio.removeEventListener("loadedmetadata", onMeta);
            audio.removeEventListener("ended", onEnd);
        };
    }, [track, onNext]);

    const togglePlay = async () => {
        if (!isPlayable) return;
        const audio = audioRef.current;
        if (!audio) return;

        if (isPlaying) {
            audio.pause();
            setIsPlaying(false);
        } else {
            try {
                await audio.play();
                setIsPlaying(true);
            } catch {
                setIsPlaying(false);
            }
        }
    };

    const onSeek = (e) => {
        const v = Number(e.target.value);
        const audio = audioRef.current;
        if (!audio || !Number.isFinite(duration) || duration <= 0) return;
        const next = (v / 100) * duration;
        audio.currentTime = next;
        setCurrentTime(next);
    };

    if (!track) return null;

    const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

    return (
        <div className="fixed bottom-0 left-0 right-0 border-t-2 border-foreground p-4 z-30 music_player bg-base-100">
            <audio key={track?.previewUrl || track?.id || 'no-preview'} ref={audioRef} preload="metadata" />
            <div className="max-w-7xl mx-auto">
                <div className="flex md:flex-row md:items-center sm:flex-col sm:items-start justify-between gap-4 w-full">
                    <div className="flex items-center gap-3 w-full sm:w-auto flex-1 min-w-0">
                        <div className="avatar">
                            <div className="w-14 h-14 rounded bg-base-200 overflow-hidden">
                                {track.image ? (
                                    <img src={track.image} alt={track.title || "cover"} />
                                ) : (
                                    <div className="flex items-center justify-center h-full">
                                        <Play className="w-6 h-6 text-foreground" />
                                    </div>
                                )}
                            </div>
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold truncate text-sm text-foreground">{track.title || "Titre inconnu"}</p>
                            <p className="text-xs text-foreground/70 truncate">{track.artists || "Artiste inconnu"}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            className="btn btn-ghost btn-sm btn-circle text-foreground hover:bg-foreground/10"
                            onClick={onPrev}
                            disabled={!onPrev}
                            title={!onPrev ? "Indisponible" : "Piste précédente"}
                        >
                            <SkipBack className="w-4 h-4" />
                        </button>

                        <button
                            onClick={togglePlay}
                            className={`btn btn-circle ${isPlayable ? "bg-foreground border-foreground hover:bg-foreground/80 text-background" : "btn-disabled"}`}
                            disabled={!isPlayable}
                            title={isPlayable ? (isPlaying ? "Pause" : "Lecture") : "Pas d'extrait disponible"}
                        >
                            {isPlaying ? <Pause className="w-5 h-5" /> : <Play className="w-5 h-5" />}
                        </button>

                        <button
                            className="btn btn-ghost btn-sm btn-circle text-foreground hover:bg-foreground/10"
                            onClick={onNext}
                            disabled={!onNext}
                            title={!onNext ? "Indisponible" : "Piste suivante"}
                        >
                            <SkipForward className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="hidden sm:flex items-center gap-3 flex-1 max-w-md">
                        <span className="text-xs text-foreground">{formatTime(currentTime)}</span>
                        <input
                            type="range"
                            min="0"
                            max="100"
                            value={Number.isFinite(progress) ? progress : 0}
                            className="range range-primary range-xs"
                            onChange={onSeek}
                        />
                        <span className="text-xs text-foreground">{formatTime(duration)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default MusicPlayer;