import styles from '../Chart.module.scss';
import React from 'react';
import { FIELDS, LABELS, TYPE_SONGS } from '../ChartPage';
import { Th } from './Grid';
import { formatDuration } from '../../../utils';
import { Song } from '../../../types';
import { AppleLink } from "../../../components/UI";

/**
 * "songs" grid type representation.
 */
export default function SongTable({
  rows,
  charted,
  currentSort,
  gridType,
  index,
  onSortChange,
}: {
  rows: Song[];
  charted: boolean;
  currentSort: string | null;
  gridType: string;
  index: string;
  onSortChange: any;
}) {
  const hasTimestamp = gridType === TYPE_SONGS && index === 'spins';

  return (
    <table className={styles.table}>
      <thead>
      <tr>
        {charted && <th>Rank</th>}
        {hasTimestamp && (
          <Th
            name="spin_timestamp"
            label="Timestamp"
            placeholder="datetime"
            rangeFilter
            sortable
            currentSort={currentSort}
            onSortChange={onSortChange}
          />
        )}
        <th />

        <Th
          name={FIELDS.artist}
          label={LABELS[FIELDS.artist]}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />
        <Th
          name={FIELDS.song}
          label={LABELS[FIELDS.song]}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />
        <Th
          name={FIELDS.release}
          label={LABELS[FIELDS.release]}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />
        <Th name={'label_name'} label="Label" />
        <Th name={'release_genre'} label="Genre" />
        <Th
          name={'release_year_released'}
          label="Released"
          placeholder={'[year] or [from]-[to]'}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />

        <th>Data</th>
      </tr>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          {/*{charted && <td>{row.rank}</td>}*/}
          {hasTimestamp && <td>{row.spin_timestamp}</td>}
          <td>{row.cover_art_url && <img src={row.cover_art_url} width="50" alt={row.release_title} />}</td>

          <td>{row.artist_id ? <AppleLink aId={row.artist_id}>{row.artist_name}</AppleLink> : row.artist_name}</td>
          <td>
            {row.song_name}
            {row.song_duration && <small>&nbsp;{formatDuration(row.song_duration)}</small>}
          </td>
          <td>
            {row.collection_id ? (
              <AppleLink cId={row.collection_id}>{row.release_title}</AppleLink>
            ) : (
              row.release_title
            )}
          </td>

          <td>{row.label_name}</td>
          <td>{row.release_genre}</td>
          <td>{row.release_year_released}</td>
          <td>
            {row.release_various_artists == 1 && <span>V/A</span>}
            {row.release_medium && <span>{row.release_medium}</span>}
            {row.song_isrc && <span>ISRC {row.song_isrc}</span>}
            {row.release_upc && <span>UPC {row.release_upc}</span>}
          </td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}

function formatData(data: any) {
}
