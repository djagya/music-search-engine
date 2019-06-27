import React from 'react';
import styles from './MatchesPreview.module.scss';
import { Song } from '../../../types';
import { formatDuration } from '../../../utils';
import { AppleLink } from "../../../components/UI";

/**
 * Renders a list of matching to the entered data songs.
 */
export default function MatchesPreview({ data }: { data: Song[] }) {
  return (
    <div className={styles.container}>
      {data.map(_ => (
        <div key={_._id}>
          <Item item={_} />
        </div>
      ))}
    </div>
  );
}

function Item({ item }: { item: Song }) {
  return (
    <div className={styles.Item}>
      {item.cover_art_url && (
        <div className={styles.coverArt}>
          <img src={item.cover_art_url} alt={item.song_name} />
        </div>
      )}

      <div className={styles.info}>
        <div>
          <div className={styles.song}>
            {item.song_name}
            &nbsp;{item.song_duration && <span className={styles.extra}>{formatDuration(item.song_duration)}</span>}
          </div>
          <div className={styles.release}>
            {item.collection_id ? (
              <AppleLink cId={item.collection_id}>{item.release_title}</AppleLink>
            ) : (
              item.release_title
            )}
            &nbsp;{!!item.release_various_artists && <span className={styles.extra}>V/A</span>}
          </div>
          <div className={styles.artist}>{item.artist_name}</div>
        </div>

        <div>
          <div className={styles.extraBlock}>
            {item.release_year_released && <span>{item.release_year_released}</span>}
            {item.release_genre && <span>{item.release_genre}</span>}
            {item.label_name && <span>"{item.label_name}"</span>}
            {item.release_medium && <span>{item.release_medium}</span>}
          </div>
          <div className={styles.extraBlock}>
            {item.song_isrc && <span>ISRC&nbsp;{item.song_isrc}</span>}
            {item.release_upc && <span>UPC&nbsp;{item.release_upc}</span>}
          </div>
        </div>

        <div className={styles.meta}>
          {item._index}, id: {item.id || `s_id ${item.song_id}, c_id ${item.collection_id}, a_id ${item.artist_id}`}
        </div>
      </div>
    </div>
  );
}
