import React, { useEffect, useRef, useState } from 'react';
import styles from './Chart.module.scss';
import { fetchChartRows } from '../../data';
import { ChartResponse } from '../../types';
import SongsTable from './components/SongsTable';
import ReleasesTable from './components/ReleasesTable';
import ArtistsTable from './components/ArtistsTable';
import { OptionSelect } from './components/GridSettings';
import Grid from './components/Grid';

const TYPE_SONGS = 'songs';
const TYPE_ARTISTS = 'artists';
const TYPE_RELEASES = 'releases';

export const FIELDS: any = {
  artist: 'artist_name',
  release: 'release_title',
  song: 'song_name',
};

export const LABELS: any = {
  artist_name: 'Artist',
  release_title: 'Release',
  song_name: 'Song',
};

export const PAGE_SIZE = 50;

const initPagination = { page: 0, pageSize: PAGE_SIZE, after: null, prev: null };

/**
 * todo: "chart mode" should probably use data only from the 'spins' index? it doesn't make sense to chart epf data?
 *
 */
export default function ChartPage() {
  const [gridType, setGridType] = useState(TYPE_ARTISTS);
  const [index, setIndex] = useState<string>('spins');
  const [chartMode, setChartMode] = useState<boolean>(false);
  const [response, setResponse] = useState<ChartResponse | null>(null);
  const formNode = useRef<HTMLFormElement>(null);

  useEffect(() => {
    setResponse(null);
  }, [gridType, index]);

  /**
   * Collect and send form data.
   */
  function fetchData(pagination: { page?: number; after?: string | null; sort?: string | null }) {
    const formData = Array.from(new FormData(formNode.current!).entries()).reduce((acc, [key, value]) => {
      return { ...acc, [key]: value.toString() };
    }, {});

    const params = {
      ...(response ? response.pagination : {}),
      ...pagination,
      pageSize: PAGE_SIZE,
      index,
    };
    console.log('fetching data', { params, formData });
    fetchChartRows(formData, params).then(res => {
      console.log('response', res);

      if ('error' in res) {
        throw new Error(res.error);
      }

      setResponse(res);
    });
  }

  function renderTable() {
    const rows = (response && response.rows) || [];
    const component: { [type: string]: any } = {
      [TYPE_SONGS]: SongsTable,
      [TYPE_ARTISTS]: ArtistsTable,
      [TYPE_RELEASES]: ReleasesTable,
    };

    return React.createElement(component[gridType], {
      rows,
      charted: chartMode,
      currentSort: response && response.pagination.sort,
      onSortChange: (sort: string | null) => {
        fetchData({ sort });
      },
    });
  }

  return (
    <div className={styles.container}>
      <form
        onSubmit={(e: any) => {
          e.preventDefault();
          fetchData({ page: 0, after: null });
        }}
        ref={formNode}
      >
        <GridSettings
          gridType={gridType}
          index={index}
          chartMode={chartMode}
          onTypeChange={e => {
            setGridType(e.currentTarget.value);
            setResponse(null);
          }}
          onIndexChange={e => {
            setIndex(e.currentTarget.value);
            setResponse(null);
          }}
          onChartModeChange={e => setChartMode(e.currentTarget.checked)}
        />
        <button className={styles.submitButton} type="submit">
          Search
        </button>
        &nbsp;
        <button type="reset" onClick={() => fetchData({ page: 0, after: null })}>
          Reset
        </button>
        <Grid response={response} onPageChange={(page: number, after: string | null) => fetchData({ page, after })}>
          {renderTable()}
        </Grid>
      </form>
    </div>
  );
}

function GridSettings({
  gridType,
  index,
  chartMode,
  onTypeChange,
  onIndexChange,
  onChartModeChange,
}: {
  gridType: string;
  index: string;
  chartMode: boolean;
  onTypeChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
  onIndexChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
  onChartModeChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
}) {
  // todo: for now chart is hidden, it's an additional feature that is not required
  return (
    <div className={styles.gridSettings}>
      <OptionSelect
        name="type"
        items={{ [TYPE_SONGS]: 'Songs', [TYPE_ARTISTS]: 'Artists', [TYPE_RELEASES]: 'Releases' }}
        active={gridType}
        onChange={onTypeChange}
      >
        Search for:
      </OptionSelect>
      <OptionSelect name="index" items={{ spins: 'Spins', epf: 'Apple Music' }} active={index} onChange={onIndexChange}>
        in:
      </OptionSelect>
      {/*<ChartModeSwitch chartMode={chartMode} onChange={onChartModeChange} />*/}
    </div>
  );
}

